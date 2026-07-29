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

use core_text;
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
    const EVENT_TYPES = ['page', 'scroll', 'cursor', 'student_click', 'resync_request', 'chat_message'];

    /** @var int Maximum length, in characters, of a chat_message payload's 'message'. */
    const MAX_CHAT_MESSAGE_LENGTH = 1000;

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

    /** @var int Maximum length, in characters, of a 'page' payload's 'inlineCss' field. */
    const MAX_INLINE_CSS_LENGTH = 40000;

    /**
     * @var int Maximum length, in characters, of a 'cursor' payload's 'hover' selector.
     * Sized generously for event_capture.js's structural-path fallback, which can now
     * walk up to HOVER_SELECTOR_MAX_DEPTH (30) ancestors deep — still tiny next to the
     * other 'page' payload fields.
     */
    const MAX_HOVER_SELECTOR_LENGTH = 1500;

    /**
     * Validate and store a new event.
     *
     * For 'page' events, the payload's 'html', 'modal' and 'fixed' fields
     * are each run through html_sanitizer before storage: this is the
     * single point where untrusted captured HTML becomes safe to relay to
     * another user. 'inlineCss' gets its own, more limited, text-based
     * cleanup (see sanitize_inline_css() below) — it is CSS, not HTML, so
     * html_sanitizer's DOM-based approach does not apply to it.
     * 'scroll', 'cursor' and 'student_click' events get a light shape check
     * of their own (numeric coordinates) — cheap to enforce and closes the
     * gap where a malformed payload would otherwise sail through as long as
     * it stayed under the overall size cap. 'cursor' additionally accepts an
     * optional 'hover' selector (bounded to MAX_HOVER_SELECTOR_LENGTH, never
     * rejects the event outright since it's auxiliary to the position).
     * 'chat_message' is always plain text,
     * rendered with textContent client-side, never interpreted as HTML —
     * no sanitizer involved, just a non-empty check and a length cap.
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

        if (!rate_limiter::is_allowed($sessionid, $sourceuserid, $eventtype)) {
            return null;
        }

        if ($eventtype === 'page') {
            if (isset($payload['html']) && is_string($payload['html'])) {
                $payload['html'] = html_sanitizer::sanitize($payload['html']);
            }
            if (isset($payload['modal']) && is_string($payload['modal'])) {
                $payload['modal'] = html_sanitizer::sanitize($payload['modal']);
            }
            if (isset($payload['fixed']) && is_string($payload['fixed'])) {
                $payload['fixed'] = html_sanitizer::sanitize($payload['fixed']);
            }
            if (isset($payload['inlineCss']) && is_string($payload['inlineCss'])) {
                $payload['inlineCss'] = self::sanitize_inline_css($payload['inlineCss']);
            }
            if (isset($payload['css']) && is_array($payload['css'])) {
                $wwwroot = $GLOBALS['CFG']->wwwroot;
                $payload['css'] = array_values(array_filter($payload['css'], static function ($url) use ($wwwroot) {
                    return is_string($url) && strpos($url, $wwwroot) === 0;
                }));
            }
        }

        if ($eventtype === 'scroll' || $eventtype === 'cursor' || $eventtype === 'student_click') {
            if (!isset($payload['x'], $payload['y']) || !is_numeric($payload['x']) || !is_numeric($payload['y'])) {
                throw new moodle_exception('errorinvalideventtype', 'local_remotesupport');
            }
        }

        if ($eventtype === 'cursor' && array_key_exists('hover', $payload)) {
            // Optional and auxiliary to the position itself, so a bad value
            // here is truncated/dropped rather than rejecting an otherwise
            // valid x/y update. Never interpreted as HTML or executed —
            // only ever used client-side as a CSS selector argument to
            // querySelector() inside the sandboxed iframe (wrapped in its
            // own try/catch there), so no further sanitization is needed
            // beyond bounding its size.
            if (is_string($payload['hover']) && $payload['hover'] !== '') {
                $payload['hover'] = core_text::substr($payload['hover'], 0, self::MAX_HOVER_SELECTOR_LENGTH);
            } else {
                $payload['hover'] = null;
            }
        }

        if ($eventtype === 'chat_message') {
            if (!isset($payload['message']) || !is_string($payload['message'])) {
                throw new moodle_exception('errorinvalideventtype', 'local_remotesupport');
            }
            $message = trim($payload['message']);
            if ($message === '') {
                throw new moodle_exception('errorinvalideventtype', 'local_remotesupport');
            }
            if (core_text::strlen($message) > self::MAX_CHAT_MESSAGE_LENGTH) {
                $message = core_text::substr($message, 0, self::MAX_CHAT_MESSAGE_LENGTH);
            }
            $payload['message'] = $message;
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
     * Best-effort text-based cleanup of captured inline `<style>` CSS,
     * relayed verbatim into a `<style>` tag inside the teacher's sandboxed
     * iframe (see screen_renderer.js). Unlike HTML, PHP has no built-in CSS
     * parser to validate this properly (the way DOMDocument does for
     * html_sanitizer::sanitize()), so rather than attempt a real parse this
     * strips the two constructs that could otherwise make the teacher's
     * browser reach out to an arbitrary URL when it renders the
     * reconstruction: `@import` (would fetch a whole external stylesheet)
     * and any `url(...)` reference (background images, @font-face, etc. —
     * legitimate uses are lost along with it, an accepted trade-off given
     * script execution is already blocked at the iframe sandbox level
     * regardless; this is defense in depth, not the only layer). See
     * docs/decisions.md.
     *
     * @param string $css
     * @return string
     */
    private static function sanitize_inline_css(string $css): string {
        $css = (string) preg_replace('/@import[^;]*;/i', '', $css);
        $css = (string) preg_replace('/url\([^)]*\)/i', 'none', $css);

        if (core_text::strlen($css) > self::MAX_INLINE_CSS_LENGTH) {
            $css = core_text::substr($css, 0, self::MAX_INLINE_CSS_LENGTH);
        }

        return $css;
    }

    /**
     * Fetch events for a session with id greater than $sinceid, in order,
     * marking them as consumed.
     *
     * Excludes events sourced by $excludeuserid: a recipient only ever wants
     * events from the *other* party (the student never needs to see their
     * own page/scroll events echoed back, and vice versa for the teacher's
     * resync_request events), so this doubles as the "who receives what"
     * rule without a separate recipient column. 'chat_message' is the one
     * exception: both participants need to see the *whole* conversation,
     * including their own messages, so a fresh page load (which always
     * restarts $sinceid at 0 — this plugin re-injects its capture script on
     * every Moodle page) naturally replays the full chat history alongside
     * whatever's new, with no separate "history" endpoint needed.
     *
     * @param int $sessionid
     * @param int $sinceid
     * @param int $excludeuserid Events sourced by this user are skipped, except chat_message.
     * @param int $limit
     * @return \stdClass[] Ordered oldest-first, each with 'payload' already json_decode()d.
     */
    public static function get_events_since(int $sessionid, int $sinceid, int $excludeuserid, int $limit = self::DEFAULT_PULL_LIMIT): array {
        global $DB;

        $events = $DB->get_records_select(
            'local_remotesupport_event',
            "sessionid = :sessionid AND id > :sinceid AND (sourceuserid != :excludeuserid OR eventtype = :chattype)",
            ['sessionid' => $sessionid, 'sinceid' => $sinceid, 'excludeuserid' => $excludeuserid, 'chattype' => 'chat_message'],
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
     * 'chat_message' rows are deliberately exempt: unlike a stale 'page' or
     * 'scroll' snapshot (harmless to lose, the next heartbeat replaces it),
     * a chat message is content nobody can regenerate. It still cannot
     * outlive the session itself — purge_session_events() removes it like
     * everything else the moment the session closes.
     *
     * @param int $olderthanseconds
     * @return int Number of rows deleted.
     */
    public static function purge_stale_events(int $olderthanseconds = 120): int {
        global $DB;

        $cutoff = time() - $olderthanseconds;
        $select = 'timecreated < :cutoff AND eventtype != :chattype';
        $params = ['cutoff' => $cutoff, 'chattype' => 'chat_message'];
        $count = $DB->count_records_select('local_remotesupport_event', $select, $params);
        $DB->delete_records_select('local_remotesupport_event', $select, $params);

        return $count;
    }
}
