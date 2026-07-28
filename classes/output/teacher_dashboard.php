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

namespace local_remotesupport\output;

use context_course;
use core_user;
use local_remotesupport\local\session_manager;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the teacher_dashboard.mustache template context.
 *
 * Shared by view.php's first (no-JS) render and get_teacher_dashboard's AJAX
 * response, so the two can never disagree on which rows or actions are shown.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_dashboard {

    /**
     * @param int $teacherid
     * @return array Rows for the pending-requests table, including accepturl.
     */
    public static function export_pending(int $teacherid): array {
        $rows = [];
        foreach (session_manager::get_pending_requests_for_teacher($teacherid) as $session) {
            $course = get_course($session->courseid);
            $coursecontext = context_course::instance($course->id);
            $rows[] = [
                'sessionid' => (int) $session->id,
                'studentname' => fullname(core_user::get_user($session->studentid)),
                'coursename' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'waitingsince' => userdate($session->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'reason' => $session->reason ?? '',
                'accepturl' => (new moodle_url('/local/remotesupport/view.php', [
                    'action' => 'accept', 'sessionid' => $session->id, 'sesskey' => sesskey(),
                ]))->out(false),
            ];
        }
        return $rows;
    }

    /**
     * @param int $teacherid
     * @return array Rows for the open-sessions table, including enterurl/finishurl.
     */
    public static function export_open(int $teacherid): array {
        $rows = [];
        foreach (session_manager::get_open_sessions_for_teacher($teacherid) as $session) {
            $course = get_course($session->courseid);
            $coursecontext = context_course::instance($course->id);
            $rows[] = [
                'sessionid' => (int) $session->id,
                'studentname' => fullname(core_user::get_user($session->studentid)),
                'coursename' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'status' => get_string('sessionstatus_' . $session->status, 'local_remotesupport'),
                'enterurl' => (new moodle_url('/local/remotesupport/view.php', [
                    'action' => 'enter', 'sessionid' => $session->id, 'sesskey' => sesskey(),
                ]))->out(false),
                'finishurl' => (new moodle_url('/local/remotesupport/view.php', [
                    'action' => 'finish', 'sessionid' => $session->id, 'sesskey' => sesskey(),
                ]))->out(false),
            ];
        }
        return $rows;
    }

    /**
     * Full template context, including action urls — same shape for the
     * first PHP render and every AJAX refresh.
     *
     * @param int $teacherid
     * @return array
     */
    public static function export(int $teacherid): array {
        $pending = self::export_pending($teacherid);
        $open = self::export_open($teacherid);

        return [
            'haspending' => (bool) $pending,
            'pending' => $pending,
            'hasopen' => (bool) $open,
            'open' => $open,
            'hassettings' => (bool) get_user_capability_course('local/remotesupport:provideassistance', $teacherid, false),
            'settingsurl' => (new moodle_url('/local/remotesupport/teachersettings.php'))->out(false),
            'hashistory' => (bool) get_user_capability_course('local/remotesupport:viewsessionhistory', $teacherid, false),
            'historyurl' => (new moodle_url('/local/remotesupport/sessionhistory.php'))->out(false),
        ];
    }
}
