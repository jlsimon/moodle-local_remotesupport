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
 * Teacher page: personal preferences for providing remote assistance.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_remotesupport\local\teacher_settings;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/remotesupport/teachersettings.php'));
$PAGE->set_title(get_string('pagetitle_settings', 'local_remotesupport'));
$PAGE->set_heading(get_string('pagetitle_settings', 'local_remotesupport'));
$PAGE->set_pagelayout('standard');

$teachingcourses = get_user_capability_course('local/remotesupport:provideassistance', $USER->id, false);
if (!$teachingcourses) {
    throw new moodle_exception('errornopermission', 'local_remotesupport');
}

if (optional_param('action', '', PARAM_ALPHA) === 'save') {
    require_sesskey();
    $enabled = optional_param('supportenabled', 0, PARAM_BOOL);
    teacher_settings::set_support_enabled((int) $USER->id, (bool) $enabled);
    redirect(
        new moodle_url('/local/remotesupport/teachersettings.php'),
        get_string('settingssaved', 'local_remotesupport')
    );
}

$data = [
    'formurl' => (new moodle_url('/local/remotesupport/teachersettings.php'))->out(false),
    'sesskey' => sesskey(),
    'supportenabled' => teacher_settings::is_support_enabled((int) $USER->id),
    'backurl' => (new moodle_url('/local/remotesupport/view.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_remotesupport/teacher_settings', $data);
echo $OUTPUT->footer();
