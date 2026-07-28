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
 * Teacher page: sortable list of past (closed) assistance sessions.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_remotesupport\local\permission_manager;
use local_remotesupport\table\session_history_table;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/remotesupport/sessionhistory.php'));
$PAGE->set_title(get_string('pagetitle_history', 'local_remotesupport'));
$PAGE->set_heading(get_string('pagetitle_history', 'local_remotesupport'));
$PAGE->set_pagelayout('standard');

permission_manager::require_can_view_history((int) $USER->id);

// Standard Moodle idiom for a "rows per page" selector (same pattern as
// tool_dataprivacy's request list and the grader report): remembered as a
// user preference, only overridden when the GET param is actually present
// this request (i.e. the select was just submitted).
$perpageoptions = [10 => 10, 20 => 20, 50 => 50, 100 => 100];

$perpage = optional_param('perpage', 0, PARAM_INT);
if (array_key_exists($perpage, $perpageoptions)) {
    set_user_preference(session_history_table::PREF_PERPAGE, $perpage);
} else {
    $perpage = (int) get_user_preferences(session_history_table::PREF_PERPAGE, 20);
    if (!array_key_exists($perpage, $perpageoptions)) {
        $perpage = 20;
    }
}

$table = new session_history_table((int) $USER->id);
$table->define_baseurl($PAGE->url);

$perpageselect = new single_select($PAGE->url, 'perpage', $perpageoptions, $perpage, null);
$perpageselect->label = get_string('perpage', 'moodle');

echo $OUTPUT->header();
echo html_writer::div($OUTPUT->render($perpageselect), 'local-remotesupport-perpage');
$table->out($perpage, true);
echo $OUTPUT->footer();
