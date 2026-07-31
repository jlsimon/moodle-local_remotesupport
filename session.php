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
 * Shared active-session page for student and teacher.
 *
 * Reached only via a one-time link containing a token issued by
 * session_manager::issue_entry_token() from request.php or view.php. The
 * teacher gets the reconstruction view rendered here; the student is
 * redirected straight on to wherever they requested assistance from (or
 * the course page, see $destination below) — this page itself renders
 * nothing for them, on purpose (see docs/decisions.md).
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_remotesupport\local\session_manager;

require_login();

$sessionid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'finish') {
    require_sesskey();
    $session = session_manager::close_session($sessionid, $USER->id);
    $returnurl = ((int) $session->studentid === (int) $USER->id)
        ? new moodle_url('/local/remotesupport/request.php', ['id' => $session->courseid])
        : new moodle_url('/local/remotesupport/view.php');
    redirect($returnurl, get_string('sessionclosed', 'local_remotesupport'));
}

$token = required_param('token', PARAM_ALPHANUM);
$session = session_manager::enter_session($sessionid, $USER->id, $token);
$course = get_course($session->courseid);
$context = context_course::instance($course->id);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/remotesupport/session.php', ['id' => $sessionid]));
$PAGE->set_title(get_string('pagetitle_session', 'local_remotesupport'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('standard');

$isstudent = ((int) $session->studentid === (int) $USER->id);

if ($isstudent) {
    // Straight back into browsing, no extra confirmation click: the
    // status bar (injected on whatever page this lands on, via
    // event_capture.js/lib.php's before_footer hook) already carries its
    // own "Finalizar" action, so nothing is lost by not stopping here.
    // Prefers the page the student was actually on when they requested
    // assistance (session_manager::create_request()'s $returnurl) over
    // the course's front page — falls back to that if there is none
    // stored (older requests, or a url that no longer parses as local).
    $destination = null;
    if (!empty($session->returnurl)) {
        try {
            $destination = new moodle_url($session->returnurl);
        } catch (moodle_exception $e) {
            $destination = null;
        }
    }
    if (!$destination) {
        $destination = new moodle_url('/course/view.php', ['id' => $course->id]);
    }

    $teacher = core_user::get_user($session->teacherid);
    redirect($destination, get_string('heading_session_student', 'local_remotesupport', fullname($teacher)));
}

$finishurl = (new moodle_url('/local/remotesupport/session.php', [
    'id' => $sessionid, 'action' => 'finish', 'sesskey' => sesskey(),
]))->out(false);

$student = core_user::get_user($session->studentid);
$data = [
    'heading' => get_string('heading_session_teacher', 'local_remotesupport', fullname($student)),
    'finishurl' => $finishurl,
];
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_remotesupport/session_player', $data);
$PAGE->requires->js_call_amd('local_remotesupport/event_player', 'init', [
    $session->id,
    (int) $USER->id,
    fullname($student),
    (bool) get_config('local_remotesupport', 'clicksound'),
    (bool) get_config('local_remotesupport', 'enableteacherpointer'),
]);
echo $OUTPUT->footer();
