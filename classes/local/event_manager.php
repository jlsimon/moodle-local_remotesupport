<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_remotesupport\local;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Owns the local_remotesupport_event table.
 *
 * Callers are responsible for authorization (who may push/pull for a given
 * session) and for confirming the session is in a state that allows events
 * (status active); this class only knows how to validate, store, and purge
 * event rows. The event's own `id` doubles as the strictly increasing read
 * cursor, so there is no separate sequence column to keep consistent.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_manager {

    /** @var string[] The only event types accepted so far. */
    const EVENT_TYPES = [
        'page', 'scroll', 'cursor', 'highlight', 'resync_request',
        'click_request', 'click_result', 'input_request', 'input_result',
        'scroll_request',
    ];

    /** @var string[] Recognised values for an 'input_request' payload's 'action'. */
    const INPUT_ACTIONS = ['set_value', 'append_text', 'clear'];

    /** @var int Maximum length, in characters, of an 'input_request' payload's 'value'. */
    const MAX_INPUT_VALUE_LENGTH = 4000;

    /**
     * @var int Maximum size, in bytes, of a JSON-encoded event payload.
     * Sized to comfortably fit a 'page' event's main html (capped at
     * html_sanitizer::MAX_LENGTH, 400000 chars — large enough for a
     * 'fullpage'-mode capture, not just 'main') plus an open modal's html
     * (capped separately, much smaller in practice, and only sent in 'main'
     * mode) plus a short list of stylesheet URLs, generously accounting for
     * UTF-8 multi-byte characters and JSON escaping overhead.
     */
    const MAX_PAYLOAD_BYTES = 600000;

    /** @var int Default number of events returned per pull. */
    const DEFAULT_PULL_LIMIT = 20;

    /**
     * Validate and store a new event.
     *
     * For 'page' events, the payload's 'html' field is run through
     * html_sanitizer before storage: this is the single point where
     * untrusted captured HTML becomes safe to relay to another user.
     * Other frequent, small event types ('cursor', 'scroll',
     * 'scroll_request', 'highlight') get a light shape check of their own
     * (numeric coordinates, non-empty selector) — cheap to enforce and
     * closes the gap where a malformed payload would otherwise sail
     * through as long as it stayed under the overall size cap.
     *
     * @param int $sessionid
     * @param int $sourceuserid
     * @param string $eventtype One of self::EVENT_TYPES.
     * @param array $payload
     * @return \stdClass|null The stored event row, or null if dropped by rate_limiter.
     * @throws moodle_exception If the type is not recognised, the payload's shape doesn't
     *                          match what that type expects, or the payload is too large.
     */
    public static function record_event(int $sessionid, int $sourceuserid, string $eventtype, array $payload): ?\stdClass {
        global $DB;

        if (!in_array($eventtype, self::EVENT_TYPES, true)) {
            throw new moodle_exception('errorinvalideventtype', 'local_remotesupport');
        }

        if (!rate_limiter::is_allowed($sessionid, $eventtype)) {
            return null;
        }

        if ($eventtype === 'page') {
            if (isset($payload['html']) && is_string($payload['html'])) {
                $payload['html'] = html_sanitizer::sanitize($payload['html']);
            }
            if (isset($payload['modal']) && is_string($payload['modal'])) {
                $payload['modal'] = html_sanitizer::sanitize($payload['modal']);
            }
            if (isset($payload['css']) && is_array($payload['css'])) {
                $wwwroot = $GLOBALS['CFG']->wwwroot;
                $payload['css'] = array_values(array_filter($payload['css'], static function ($url) use ($wwwroot) {
                    return is_string($url) && strpos($url, $wwwroot) === 0;
                }));
            }
        }

        if ($eventtype === 'input_request') {
            if (!isset($payload['action']) || !in_array($payload['action'], self::INPUT_ACTIONS, true)) {
                throw new moodle_exception('errorinvalideventtype', 'local_remotesupport');
            }
            if (isset($payload['value']) && is_string($payload['value']) && strlen($payload['value']) > self::MAX_INPUT_VALUE_LENGTH) {
                $payload['value'] = substr($payload['value'], 0, self::MAX_INPUT_VALUE_LENGTH);
            }
        }

        if (in_array($eventtype, ['cursor', 'scroll', 'scroll_request'], true)) {
            if (!isset($payload['x'], $payload['y']) || !is_numeric($payload['x']) || !is_numeric($payload['y'])) {
                throw new moodle_exception('errorinvalideventtype', 'local_remotesupport');
            }
        }

        if ($eventtype === 'highlight') {
            if (!isset($payload['selector']) || !is_string($payload['selector']) || $payload['selector'] === '') {
                throw new moodle_exception('errorinvalideventtype', 'local_remotesupport');
            }
        }

        $encoded = json_encode($payload);
        if ($encoded === false || strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            throw new moodle_exception('erroreventtoolarge', 'local_remotesupport');
        }

        $record = new \stdClass();
        $record->sessionid = $sessionid;
        $record->sourceuserid = $sourceuserid;
        $record->eventtype = $eventtype;
        $record->payload = $encoded;
        $record->timecreated = time();
        $record->consumed = 0;
        $record->id = $DB->insert_record('local_remotesupport_event', $record);

        return $record;
    }

    /**
     * Fetch events for a session with id greater than $sinceid, in order,
     * marking them as consumed.
     *
     * Excludes events sourced by $excludeuserid: a recipient only ever wants
     * events from the *other* party (the student never needs to see their
     * own page/scroll events echoed back, and vice versa for the teacher's
     * cursor/highlight events), so this doubles as the "who receives what"
     * rule without a separate recipient column.
     *
     * @param int $sessionid
     * @param int $sinceid
     * @param int $excludeuserid Events sourced by this user are skipped.
     * @param int $limit
     * @return \stdClass[] Ordered oldest-first, each with 'payload' already json_decode()d.
     */
    public static function get_events_since(int $sessionid, int $sinceid, int $excludeuserid, int $limit = self::DEFAULT_PULL_LIMIT): array {
        global $DB;

        $events = $DB->get_records_select(
            'local_remotesupport_event',
            'sessionid = :sessionid AND id > :sinceid AND sourceuserid != :excludeuserid',
            ['sessionid' => $sessionid, 'sinceid' => $sinceid, 'excludeuserid' => $excludeuserid],
            'id ASC',
            '*',
            0,
            $limit
        );

        if (!$events) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($events), SQL_PARAMS_QM);
        $DB->set_field_select('local_remotesupport_event', 'consumed', 1, "id {$insql}", $inparams);

        foreach ($events as $event) {
            $event->payload = json_decode($event->payload, true) ?? [];
        }

        return array_values($events);
    }

    /**
     * Delete every event belonging to a session. Called when a session
     * leaves an open state (closed, expired, cancelled).
     *
     * @param int $sessionid
     */
    public static function purge_session_events(int $sessionid): void {
        global $DB;
        $DB->delete_records('local_remotesupport_event', ['sessionid' => $sessionid]);
    }

    /**
     * Delete events older than the given age, regardless of session state.
     * Safety net for abandoned or long-running sessions; called from the
     * purge_events scheduled task.
     *
     * @param int $olderthanseconds
     * @return int Number of rows deleted.
     */
    public static function purge_stale_events(int $olderthanseconds = 120): int {
        global $DB;

        $cutoff = time() - $olderthanseconds;
        $count = $DB->count_records_select('local_remotesupport_event', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select('local_remotesupport_event', 'timecreated < :cutoff', ['cutoff' => $cutoff]);

        return $count;
    }
}
