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

use core_user;
use local_remotesupport\local\session_manager;
use local_remotesupport\local\teacher_settings;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the student_page.mustache template context.
 *
 * Shared by request.php's first (no-JS) render and get_student_status's AJAX
 * response, so the two can never disagree on wording or on which buttons are
 * shown for a given session state.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_status {

    /**
     * @param int $courseid
     * @param int $studentid
     * @return array Template context for local_remotesupport/student_page.
     */
    public static function export(int $courseid, int $studentid): array {
        $session = session_manager::get_open_request_for_student($studentid, $courseid);

        $data = [
            'accesslevel' => get_string('level_' . ($session->controllevel ?? 'view'), 'local_remotesupport'),
            'isnone' => false,
            'isrequested' => false,
            'isaccepted' => false,
            'isactive' => false,
            'sessionid' => $session->id ?? 0,
        ];

        if (!$session) {
            $data['isnone'] = true;
            $data['supportavailable'] = teacher_settings::is_support_available_for_course($courseid);
            if ($data['supportavailable']) {
                $data['statusmessage'] = get_string('status_none', 'local_remotesupport');
                $data['requestformurl'] = (new moodle_url('/local/remotesupport/request.php', [
                    'id' => $courseid,
                ]))->out(false);
                $data['sesskey'] = sesskey();
                $data['maxreasonlength'] = session_manager::MAX_REASON_LENGTH;
            } else {
                $data['statusmessage'] = get_string('status_nosupport', 'local_remotesupport');
            }
        } else if ($session->status === session_manager::STATUS_REQUESTED) {
            $data['isrequested'] = true;
            $data['statusmessage'] = get_string('status_requested', 'local_remotesupport');
            $data['cancelurl'] = (new moodle_url('/local/remotesupport/request.php', [
                'id' => $courseid, 'action' => 'cancel', 'sessionid' => $session->id, 'sesskey' => sesskey(),
            ]))->out(false);
        } else {
            $teacher = fullname(core_user::get_user($session->teacherid));
            $data['enterurl'] = (new moodle_url('/local/remotesupport/request.php', [
                'id' => $courseid, 'action' => 'enter', 'sessionid' => $session->id, 'sesskey' => sesskey(),
            ]))->out(false);
            $data['finishurl'] = (new moodle_url('/local/remotesupport/request.php', [
                'id' => $courseid, 'action' => 'finish', 'sessionid' => $session->id, 'sesskey' => sesskey(),
            ]))->out(false);

            if ($session->status === session_manager::STATUS_ACCEPTED) {
                $data['isaccepted'] = true;
                $data['statusmessage'] = get_string('status_accepted', 'local_remotesupport', $teacher);
            } else {
                $data['isactive'] = true;
                $data['statusmessage'] = get_string('status_active', 'local_remotesupport', $teacher);
            }
        }

        return $data;
    }
}
