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

namespace local_remotesupport\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use context_system;
use local_remotesupport\local\permission_manager;
use local_remotesupport\output\teacher_dashboard;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint the teacher's browser polls to refresh the pending-requests
 * and open-sessions lists without reloading view.php.
 *
 * Not scoped to a single course context (a teacher's pending requests can
 * span several courses), so this validates against the system context, the
 * same context view.php itself uses.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_teacher_dashboard extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * @return array
     */
    public static function execute(): array {
        global $USER;

        self::validate_context(context_system::instance());
        permission_manager::require_can_view_dashboard((int) $USER->id);

        return teacher_dashboard::export((int) $USER->id);
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'haspending' => new external_value(PARAM_BOOL, 'Whether there is at least one pending request'),
            'pending' => new external_multiple_structure(new external_single_structure([
                'sessionid' => new external_value(PARAM_INT, 'Session id'),
                'studentname' => new external_value(PARAM_TEXT, 'Student full name'),
                'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
                'waitingsince' => new external_value(PARAM_TEXT, 'When the request was created, formatted'),
                'reason' => new external_value(PARAM_RAW, 'Optional free-text reason given by the student'),
                'accepturl' => new external_value(PARAM_URL, 'Accept action url'),
            ])),
            'hasopen' => new external_value(PARAM_BOOL, 'Whether there is at least one open session'),
            'open' => new external_multiple_structure(new external_single_structure([
                'sessionid' => new external_value(PARAM_INT, 'Session id'),
                'studentname' => new external_value(PARAM_TEXT, 'Student full name'),
                'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
                'status' => new external_value(PARAM_TEXT, 'Human readable session status'),
                'enterurl' => new external_value(PARAM_URL, 'Enter action url'),
                'finishurl' => new external_value(PARAM_URL, 'Finish action url'),
            ])),
            'hassettings' => new external_value(PARAM_BOOL, 'Whether to show the link to teachersettings.php'),
            'settingsurl' => new external_value(PARAM_URL, 'Url of teachersettings.php'),
        ]);
    }
}
