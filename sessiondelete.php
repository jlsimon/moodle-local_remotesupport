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
 * Teacher page: bulk-delete closed sessions selected on sessionhistory.php.
 *
 * Classic two-step Moodle confirm flow, no JavaScript: the first POST (from
 * sessionhistory.php's own form) validates the selection and shows
 * $OUTPUT->confirm(); the confirmed POST (from that confirmation screen's own
 * form) actually deletes. Nothing is deleted on the first hit.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_remotesupport\local\permission_manager;
use local_remotesupport\local\session_manager;

require_login();
require_sesskey();

$context = context_system::instance();
$PAGE->set_context($context);
$url = new moodle_url('/local/remotesupport/sessiondelete.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('pagetitle_history', 'local_remotesupport'));
$PAGE->set_heading(get_string('pagetitle_history', 'local_remotesupport'));
$PAGE->set_pagelayout('standard');

$historyurl = new moodle_url('/local/remotesupport/sessionhistory.php');

// The initial submission from sessionhistory.php sends ids[] as a real
// array (one checkbox per selected row); the confirmation screen's own
// continue button resubmits the same ids collapsed into a single
// comma-separated 'idlist' string instead, under its own param name — never
// 'ids' — since single_button/moodle_url has no clean way to carry a
// repeated array param through a generated form, and calling
// optional_param() with a scalar PARAM type on a param that might actually
// hold an array throws (clean_param() explicitly refuses arrays).
$idsarray = optional_param_array('ids', [], PARAM_INT);
$idssequence = optional_param('idlist', '', PARAM_SEQUENCE);
$ids = $idsarray ?: ($idssequence !== '' ? array_map('intval', explode(',', $idssequence)) : []);
$confirmed = optional_param('confirm', 0, PARAM_BOOL);

if (!$ids) {
    redirect(
        $historyurl,
        get_string('errornosessionselected', 'local_remotesupport'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$sessions = [];
foreach ($ids as $id) {
    $session = session_manager::get_session($id);
    permission_manager::require_can_delete_session_history($session, (int) $USER->id);
    if ($session->status !== session_manager::STATUS_CLOSED) {
        throw new moodle_exception('errorinvalidstatetransition', 'local_remotesupport');
    }
    $sessions[$id] = $session;
}

if ($confirmed) {
    $deleted = session_manager::delete_sessions(array_keys($sessions), (int) $USER->id);
    redirect(
        $historyurl,
        get_string('notice_sessionsdeleted', 'local_remotesupport', $deleted),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$names = [];
foreach ($sessions as $id => $session) {
    $course = get_course($session->courseid);
    $student = core_user::get_user($session->studentid);
    // Same short format as session_history_table::col_timestarted(), so the
    // date shown here matches what the teacher just saw in the table row
    // they selected.
    $date = $session->timestarted
        ? userdate($session->timestarted, get_string('strftimedatetimeshort', 'langconfig'))
        : '-';
    $names[] = get_string('confirmdeletesessions_row', 'local_remotesupport', (object) [
        'id' => $id,
        'course' => format_string($course->fullname),
        'student' => s(fullname($student)),
        'date' => $date,
    ]);
}

$continueurl = new moodle_url($url, [
    'idlist' => implode(',', array_keys($sessions)),
    'confirm' => 1,
    'sesskey' => sesskey(),
]);
// The confirm() output renderer wraps $message in a single <p>, so the
// per-session detail is joined with <br> rather than a <ul> — a block-level
// list would end up invalidly nested inside that <p>.
$message = get_string('confirmdeletesessions', 'local_remotesupport', count($sessions)) .
    html_writer::empty_tag('br') . html_writer::empty_tag('br') .
    implode(html_writer::empty_tag('br'), $names);

echo $OUTPUT->header();
echo $OUTPUT->confirm($message, $continueurl, $historyurl);
echo $OUTPUT->footer();
