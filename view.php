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
 * Teacher page: list pending assistance requests and open sessions, accept requests.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_remotesupport\local\permission_manager;
use local_remotesupport\local\session_manager;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/remotesupport/view.php'));
$PAGE->set_title(get_string('pagetitle_view', 'local_remotesupport'));
$PAGE->set_heading(get_string('pagetitle_view', 'local_remotesupport'));
$PAGE->set_pagelayout('standard');

$teachingcourses = get_user_capability_course('local/remotesupport:viewactivesessions', $USER->id, false);
if (!$teachingcourses && !permission_manager::can_manage()) {
    throw new moodle_exception('errornopermission', 'local_remotesupport');
}

$action = optional_param('action', '', PARAM_ALPHA);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$returnurl = new moodle_url('/local/remotesupport/view.php');

if ($action !== '') {
    require_sesskey();

    if ($action === 'accept' && $sessionid) {
        session_manager::accept_request($sessionid, $USER->id);
        $token = session_manager::issue_entry_token($sessionid, $USER->id);
        redirect(new moodle_url('/local/remotesupport/session.php', ['id' => $sessionid, 'token' => $token]));
    } else if ($action === 'enter' && $sessionid) {
        $token = session_manager::issue_entry_token($sessionid, $USER->id);
        redirect(new moodle_url('/local/remotesupport/session.php', ['id' => $sessionid, 'token' => $token]));
    } else if ($action === 'finish' && $sessionid) {
        session_manager::close_session($sessionid, $USER->id);
        redirect($returnurl, get_string('sessionclosed', 'local_remotesupport'));
    }
}

$pending = session_manager::get_pending_requests_for_teacher($USER->id);
$open = session_manager::get_open_sessions_for_teacher($USER->id);

$pendingrows = [];
foreach ($pending as $session) {
    $course = get_course($session->courseid);
    $coursecontext = context_course::instance($course->id);
    $student = core_user::get_user($session->studentid);
    $pendingrows[] = [
        'studentname' => fullname($student),
        'coursename' => format_string($course->fullname, true, ['context' => $coursecontext]),
        'waitingsince' => userdate($session->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'reason' => $session->reason ?? '',
        'accepturl' => (new moodle_url('/local/remotesupport/view.php', [
            'action' => 'accept', 'sessionid' => $session->id, 'sesskey' => sesskey(),
        ]))->out(false),
    ];
}

$openrows = [];
foreach ($open as $session) {
    $course = get_course($session->courseid);
    $coursecontext = context_course::instance($course->id);
    $student = core_user::get_user($session->studentid);
    $openrows[] = [
        'studentname' => fullname($student),
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

$canprovideanywhere = (bool) get_user_capability_course('local/remotesupport:provideassistance', $USER->id, false);

$data = [
    'haspending' => (bool) $pendingrows,
    'pending' => $pendingrows,
    'hasopen' => (bool) $openrows,
    'open' => $openrows,
    'hassettings' => $canprovideanywhere,
    'settingsurl' => (new moodle_url('/local/remotesupport/teachersettings.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_remotesupport/teacher_dashboard', $data);
echo $OUTPUT->footer();
