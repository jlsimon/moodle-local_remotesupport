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

defined('MOODLE_INTERNAL') || die();

/**
 * Owns the local_remotesupport_track table: a permanent recording of a
 * session's screen activity, kept for local_remotesupport/trackretentiondays
 * so it can be played back later. Deliberately separate from event_manager's
 * local_remotesupport_event, which stays purely ephemeral (live transport
 * only, purged within minutes) — recording is an intentional, explicit
 * exception to that ephemeral-by-default policy, made consciously alongside
 * the retention window and erasure behaviour below (see docs/decisions.md).
 *
 * Only 'page' and 'scroll' are recorded — not 'chat_message' or
 * 'resync_request' — matching the scope the user asked for ("grabación
 * completa de pantalla"); the chat transcript's own persistence is a
 * separate, already-decided design (session-lifetime only, see
 * docs/decisions.md's chat entry).
 *
 * Callers are responsible for authorization and for only ever passing
 * already-validated, already-sanitized payloads (this class does not
 * validate anything itself, matching event_manager's already-done work
 * being reused, not repeated).
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class track_manager {

    /** @var string[] Event types recorded permanently for later playback. */
    const TRACKED_EVENT_TYPES = ['page', 'scroll'];

    /**
     * Append one recorded event. Assumes $encodedpayload is already the
     * sanitized, size-checked JSON string event_manager::record_event()
     * produced for the live event — not re-validated here.
     *
     * @param int $sessionid
     * @param string $eventtype One of self::TRACKED_EVENT_TYPES.
     * @param string $encodedpayload Already JSON-encoded and sanitized.
     */
    public static function record(int $sessionid, string $eventtype, string $encodedpayload): void {
        global $DB;

        $record = new \stdClass();
        $record->sessionid = $sessionid;
        $record->eventtype = $eventtype;
        $record->payload = $encodedpayload;
        $record->timecreated = time();
        $DB->insert_record('local_remotesupport_track', $record);
    }

    /**
     * Delete every recorded event belonging to a session. Used only for
     * privacy erasure requests (see classes/privacy/provider.php) — NOT
     * called when a session merely closes normally, since the whole point
     * of this table is to survive that. A session's recording otherwise
     * only ever disappears via purge_stale_track()'s retention window.
     *
     * @param int $sessionid
     */
    public static function purge_session_track(int $sessionid): void {
        global $DB;
        $DB->delete_records('local_remotesupport_track', ['sessionid' => $sessionid]);
    }

    /**
     * Delete recorded events older than the configured retention window.
     * Called from the purge_track scheduled task.
     *
     * @param int $retentiondays Days to keep; 0 or negative deletes nothing (treated as "not configured").
     * @return int Number of rows deleted.
     */
    public static function purge_stale_track(int $retentiondays): int {
        global $DB;

        if ($retentiondays <= 0) {
            return 0;
        }

        $cutoff = time() - ($retentiondays * DAYSECS);
        $count = $DB->count_records_select('local_remotesupport_track', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select('local_remotesupport_track', 'timecreated < :cutoff', ['cutoff' => $cutoff]);

        return $count;
    }
}
