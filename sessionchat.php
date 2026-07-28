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
 * Teacher page: the full chat transcript of a closed session, alone —
 * a lighter alternative to sessionreplay.php for when only the
 * conversation matters, without loading the screen recording at all.
 *
 * Reached from the "chat" column of sessionhistory.php.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_remotesupport\local\permission_manager;
use local_remotesupport\local\session_manager;
use local_remotesupport\local\track_manager;

require_login();

$sessionid = required_param('id', PARAM_INT);
$session = session_manager::get_session($sessionid);
$context = context_course::instance($session->courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/remotesupport/sessionchat.php', ['id' => $sessionid]));
$PAGE->set_title(get_string('pagetitle_chat', 'local_remotesupport'));
$PAGE->set_heading(get_string('pagetitle_chat', 'local_remotesupport'));
$PAGE->set_pagelayout('standard');

permission_manager::require_can_replay_session($session, (int) $USER->id);

if ($session->status !== session_manager::STATUS_CLOSED) {
    throw new moodle_exception('errorinvalidstatetransition', 'local_remotesupport');
}

$course = get_course($session->courseid);
$student = core_user::get_user($session->studentid);
$teacher = core_user::get_user($session->teacherid);

$messages = [];
foreach (track_manager::get_chat_for_session($session->id) as $row) {
    $payload = json_decode($row->payload, true);
    if (!isset($payload['message']) || !is_string($payload['message'])) {
        continue;
    }
    $isteacher = ((int) $row->sourceuserid === (int) $session->teacherid);
    $messages[] = [
        'sendername' => $isteacher ? fullname($teacher) : fullname($student),
        'message' => $payload['message'],
        'time' => userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'isown' => $isteacher,
    ];
}

$data = [
    'heading' => get_string('heading_chat', 'local_remotesupport', fullname($student)),
    'coursename' => format_string($course->fullname),
    'messages' => $messages,
    'hasmessages' => (bool) $messages,
    'replayurl' => (new moodle_url('/local/remotesupport/sessionreplay.php', ['id' => $sessionid]))->out(false),
    'historyurl' => (new moodle_url('/local/remotesupport/sessionhistory.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_remotesupport/session_chat', $data);
echo $OUTPUT->footer();
