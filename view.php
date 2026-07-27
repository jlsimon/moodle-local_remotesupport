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
use local_remotesupport\output\teacher_dashboard;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/remotesupport/view.php'));
$PAGE->set_title(get_string('pagetitle_view', 'local_remotesupport'));
$PAGE->set_heading(get_string('pagetitle_view', 'local_remotesupport'));
$PAGE->set_pagelayout('standard');

permission_manager::require_can_view_dashboard((int) $USER->id);

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

$data = teacher_dashboard::export((int) $USER->id);

echo $OUTPUT->header();
echo '<div id="local-remotesupport-teacher-dashboard">';
echo $OUTPUT->render_from_template('local_remotesupport/teacher_dashboard', $data);
echo '</div>';
$PAGE->requires->js_call_amd('local_remotesupport/teacher_client', 'init', []);
echo $OUTPUT->footer();
