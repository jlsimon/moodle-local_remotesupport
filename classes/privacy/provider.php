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

namespace local_remotesupport\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_course;
use local_remotesupport\local\event_manager;
use local_remotesupport\local\teacher_settings;
use local_remotesupport\local\track_manager;
use local_remotesupport\table\session_history_table;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_remotesupport.
 *
 * A session row names two people (student and teacher); see
 * docs/limitations.md for how erasure requests are handled for such
 * dual-actor records.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\user_preference_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            teacher_settings::PREF_SUPPORT_ENABLED,
            'privacy:metadata:preference:supportenabled'
        );
        $collection->add_user_preference(
            session_history_table::PREF_PERPAGE,
            'privacy:metadata:preference:sessionhistoryperpage'
        );

        $collection->add_database_table('local_remotesupport_session', [
            'courseid' => 'privacy:metadata:local_remotesupport_session:courseid',
            'studentid' => 'privacy:metadata:local_remotesupport_session:studentid',
            'teacherid' => 'privacy:metadata:local_remotesupport_session:teacherid',
            'status' => 'privacy:metadata:local_remotesupport_session:status',
            'reason' => 'privacy:metadata:local_remotesupport_session:reason',
            'timecreated' => 'privacy:metadata:local_remotesupport_session:timecreated',
            'timestarted' => 'privacy:metadata:local_remotesupport_session:timestarted',
            'timeended' => 'privacy:metadata:local_remotesupport_session:timeended',
        ], 'privacy:metadata:local_remotesupport_session');

        $collection->add_database_table('local_remotesupport_event', [
            'sourceuserid' => 'privacy:metadata:local_remotesupport_event:sourceuserid',
            'eventtype' => 'privacy:metadata:local_remotesupport_event:eventtype',
            'payload' => 'privacy:metadata:local_remotesupport_event:payload',
            'timecreated' => 'privacy:metadata:local_remotesupport_event:timecreated',
        ], 'privacy:metadata:local_remotesupport_event');

        $collection->add_database_table('local_remotesupport_track', [
            'eventtype' => 'privacy:metadata:local_remotesupport_track:eventtype',
            'payload' => 'privacy:metadata:local_remotesupport_track:payload',
            'timecreated' => 'privacy:metadata:local_remotesupport_track:timecreated',
        ], 'privacy:metadata:local_remotesupport_track');

        return $collection;
    }

    /**
     * Export the user's own preferences (support-enabled toggle, session
     * history rows-per-page), whichever of them they have ever set.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid): void {
        $raw = get_user_preferences(teacher_settings::PREF_SUPPORT_ENABLED, null, $userid);
        if ($raw === null) {
            return;
        }

        $enabled = teacher_settings::is_support_enabled($userid);
        writer::export_user_preference(
            'local_remotesupport',
            teacher_settings::PREF_SUPPORT_ENABLED,
            $enabled ? get_string('yes') : get_string('no'),
            get_string('privacy:metadata:preference:supportenabled', 'local_remotesupport')
        );

        $perpage = get_user_preferences(session_history_table::PREF_PERPAGE, null, $userid);
        if ($perpage !== null) {
            writer::export_user_preference(
                'local_remotesupport',
                session_history_table::PREF_PERPAGE,
                $perpage,
                get_string('privacy:metadata:preference:sessionhistoryperpage', 'local_remotesupport')
            );
        }
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {local_remotesupport_session} s ON s.contextid = c.id
                 WHERE s.studentid = :studentid OR s.teacherid = :teacherid";
        $contextlist->add_from_sql($sql, ['studentid' => $userid, 'teacherid' => $userid]);

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!($context instanceof context_course)) {
            return;
        }

        $sql = "SELECT studentid, teacherid
                  FROM {local_remotesupport_session}
                 WHERE contextid = :contextid";
        $userlist->add_from_sql('studentid', $sql, ['contextid' => $context->id]);
        $userlist->add_from_sql('teacherid', $sql, ['contextid' => $context->id]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof context_course)) {
                continue;
            }

            $sessions = $DB->get_records(
                'local_remotesupport_session',
                ['contextid' => $context->id]
            );

            $data = [];
            foreach ($sessions as $session) {
                if ($session->studentid != $userid && $session->teacherid != $userid) {
                    continue;
                }
                $data[] = (object) [
                    'role' => $session->studentid == $userid ? 'student' : 'teacher',
                    'status' => $session->status,
                    'reason' => $session->reason,
                    'timecreated' => \core_privacy\local\request\transform::datetime($session->timecreated),
                    'timestarted' => $session->timestarted
                        ? \core_privacy\local\request\transform::datetime($session->timestarted) : null,
                    'timeended' => $session->timeended
                        ? \core_privacy\local\request\transform::datetime($session->timeended) : null,
                    // The recording itself (captured page/scroll events) is not
                    // dumped verbatim here — it can run to hundreds of large
                    // HTML snapshots per session, impractical to include in an
                    // export archive. A count is enough to disclose that it
                    // exists and roughly how much of it there is; retrieving
                    // the actual content is a job for the (not yet built)
                    // playback feature, gated by the same capability checks
                    // as everything else in this plugin.
                    'recordedeventcount' => $DB->count_records('local_remotesupport_track', ['sessionid' => $session->id]),
                ];
            }

            if ($data) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path', 'local_remotesupport')],
                    (object) ['sessions' => $data]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!($context instanceof context_course)) {
            return;
        }

        self::purge_sessions_and_events($DB->get_records('local_remotesupport_session', ['contextid' => $context->id]));
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof context_course)) {
                continue;
            }

            $sessions = $DB->get_records_select(
                'local_remotesupport_session',
                'contextid = :contextid AND (studentid = :studentid OR teacherid = :teacherid)',
                ['contextid' => $context->id, 'studentid' => $userid, 'teacherid' => $userid]
            );
            self::purge_sessions_and_events($sessions);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!($context instanceof context_course)) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $sessions = $DB->get_records_select(
                'local_remotesupport_session',
                'contextid = :contextid AND (studentid = :studentid OR teacherid = :teacherid)',
                ['contextid' => $context->id, 'studentid' => $userid, 'teacherid' => $userid]
            );
            self::purge_sessions_and_events($sessions);
        }
    }

    /**
     * Delete the given session rows and any events/recording still attached
     * to them. The recording is deliberately included here even though it
     * normally survives session close: an erasure request is not the same
     * as a routine close, and the recorded content is fundamentally the
     * student's own activity — see docs/decisions.md for why it is deleted
     * outright here rather than anonymised or kept for the other party.
     *
     * @param \stdClass[] $sessions
     */
    private static function purge_sessions_and_events(array $sessions): void {
        global $DB;

        if (!$sessions) {
            return;
        }

        foreach ($sessions as $session) {
            event_manager::purge_session_events($session->id);
            track_manager::purge_session_track($session->id);
        }
        $DB->delete_records_list('local_remotesupport_session', 'id', array_keys($sessions));
    }
}
