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

/**
 * Student page: request, cancel or enter a remote assistance session.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_remotesupport\local\permission_manager;
use local_remotesupport\local\session_manager;
use local_remotesupport\local\teacher_settings;

$courseid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$reason = optional_param('reason', '', PARAM_TEXT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($course->id);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/remotesupport/request.php', ['id' => $course->id]));
$PAGE->set_title(get_string('pagetitle_request', 'local_remotesupport'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('standard');

permission_manager::require_can_request($context);

$returnurl = new moodle_url('/local/remotesupport/request.php', ['id' => $course->id]);

if ($action !== '') {
    require_sesskey();

    if ($action === 'request') {
        if (!teacher_settings::is_support_available_for_course($course->id)) {
            throw new moodle_exception('errornosupportavailable', 'local_remotesupport');
        }
        session_manager::create_request($course->id, $USER->id, $reason);
        redirect($returnurl, get_string('requestcreated', 'local_remotesupport'));
    } else if ($action === 'cancel' && $sessionid) {
        session_manager::cancel_request($sessionid, $USER->id);
        redirect($returnurl, get_string('requestcancelled', 'local_remotesupport'));
    } else if ($action === 'enter' && $sessionid) {
        $token = session_manager::issue_entry_token($sessionid, $USER->id);
        redirect(new moodle_url('/local/remotesupport/session.php', ['id' => $sessionid, 'token' => $token]));
    } else if ($action === 'finish' && $sessionid) {
        session_manager::close_session($sessionid, $USER->id);
        redirect($returnurl, get_string('sessionclosed', 'local_remotesupport'));
    }
}

$session = session_manager::get_open_request_for_student($USER->id, $course->id);

$data = [
    'accesslevel' => get_string('level_' . ($session->controllevel ?? 'view'), 'local_remotesupport'),
    'isnone' => false,
    'isrequested' => false,
    'isaccepted' => false,
    'isactive' => false,
];

if (!$session) {
    $data['isnone'] = true;
    $data['supportavailable'] = teacher_settings::is_support_available_for_course($course->id);
    if ($data['supportavailable']) {
        $data['statusmessage'] = get_string('status_none', 'local_remotesupport');
        $data['requestformurl'] = $returnurl->out(false);
        $data['sesskey'] = sesskey();
        $data['maxreasonlength'] = session_manager::MAX_REASON_LENGTH;
    } else {
        $data['statusmessage'] = get_string('status_nosupport', 'local_remotesupport');
    }
} else if ($session->status === session_manager::STATUS_REQUESTED) {
    $data['isrequested'] = true;
    $data['statusmessage'] = get_string('status_requested', 'local_remotesupport');
    $data['cancelurl'] = (new moodle_url('/local/remotesupport/request.php', [
        'id' => $course->id, 'action' => 'cancel', 'sessionid' => $session->id, 'sesskey' => sesskey(),
    ]))->out(false);
} else {
    $teacher = fullname(core_user::get_user($session->teacherid));
    $enterurl = (new moodle_url('/local/remotesupport/request.php', [
        'id' => $course->id, 'action' => 'enter', 'sessionid' => $session->id, 'sesskey' => sesskey(),
    ]))->out(false);
    $finishurl = (new moodle_url('/local/remotesupport/request.php', [
        'id' => $course->id, 'action' => 'finish', 'sessionid' => $session->id, 'sesskey' => sesskey(),
    ]))->out(false);

    if ($session->status === session_manager::STATUS_ACCEPTED) {
        $data['isaccepted'] = true;
        $data['statusmessage'] = get_string('status_accepted', 'local_remotesupport', $teacher);
    } else {
        $data['isactive'] = true;
        $data['statusmessage'] = get_string('status_active', 'local_remotesupport', $teacher);
    }
    $data['enterurl'] = $enterurl;
    $data['finishurl'] = $finishurl;
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_remotesupport/student_page', $data);
echo $OUTPUT->footer();
