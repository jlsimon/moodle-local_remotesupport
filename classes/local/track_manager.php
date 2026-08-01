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

/**
 * Owns the local_remotesupport_track table: a permanent recording of a
 * session's screen activity, kept for local_remotesupport/trackretentiondays
 * so it can be played back later. Deliberately separate from event_manager's
 * local_remotesupport_event, which stays purely ephemeral (live transport
 * only, purged within minutes) — recording is an intentional, explicit
 * exception to that ephemeral-by-default policy, made consciously alongside
 * the retention window and erasure behaviour below (see docs/decisions.md).
 *
 * 'page', 'scroll', 'cursor', 'student_click' and 'chat_message' are
 * recorded — not 'resync_request', which carries no content of its own.
 * Chat messages were originally excluded (matching the scope first asked
 * for, "grabación completa de pantalla") but were added so that session
 * playback can show the conversation synchronized with the screen; see
 * docs/decisions.md's playback entry for why that revised the earlier
 * chat-persistence decision. Sessions closed before that change have no
 * chat to replay, only screen activity. 'cursor' (the student's mouse
 * position) and 'student_click' (where the student clicked) are later,
 * deliberate exceptions to the base spec's "don't store every mouse
 * movement" guidance — 'cursor' is only sent while the mouse is actually
 * moving (an event listener, not a timer), so an idle mouse generates
 * nothing, and 'student_click' only fires on an actual click, an
 * inherently infrequent event; see docs/decisions.md.
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
    const TRACKED_EVENT_TYPES = ['page', 'scroll', 'cursor', 'student_click', 'chat_message'];

    /**
     * Append one recorded event. Assumes $encodedpayload is already the
     * sanitized, size-checked JSON string event_manager::record_event()
     * produced for the live event — not re-validated here.
     *
     * @param int $sessionid
     * @param int $sourceuserid The user whose browser generated the event — only
     *                          meaningful for 'chat_message' (page/scroll/cursor/student_click
     *                          are always the student's), but recorded uniformly for simplicity.
     * @param string $eventtype One of self::TRACKED_EVENT_TYPES.
     * @param string $encodedpayload Already JSON-encoded and sanitized.
     */
    public static function record(int $sessionid, int $sourceuserid, string $eventtype, string $encodedpayload): void {
        global $DB;

        $record = new \stdClass();
        $record->sessionid = $sessionid;
        $record->sourceuserid = $sourceuserid;
        $record->eventtype = $eventtype;
        $record->payload = $encodedpayload;
        $record->timecreated = time();
        $DB->insert_record('local_remotesupport_track', $record);
    }

    /**
     * Fetch a session's whole recording, oldest first, for playback. The
     * id column doubles as the playback order (see install.xml), same
     * pattern as event_manager's read cursor.
     *
     * @param int $sessionid
     * @return \stdClass[] Each with 'payload' still JSON-encoded, as stored.
     */
    public static function get_track_for_session(int $sessionid): array {
        global $DB;
        return array_values($DB->get_records('local_remotesupport_track', ['sessionid' => $sessionid], 'id ASC'));
    }

    /**
     * Fetch only the chat messages of a session's recording, oldest first —
     * for a chat-only view (`sessionchat.php`) that skips downloading the
     * (potentially large) page/scroll payloads a full replay would need.
     *
     * @param int $sessionid
     * @return \stdClass[] Each with 'payload' still JSON-encoded, as stored.
     */
    public static function get_chat_for_session(int $sessionid): array {
        global $DB;
        return array_values($DB->get_records(
            'local_remotesupport_track',
            ['sessionid' => $sessionid, 'eventtype' => 'chat_message'],
            'id ASC'
        ));
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
