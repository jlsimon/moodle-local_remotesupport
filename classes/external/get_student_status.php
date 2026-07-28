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
use context_course;
use local_remotesupport\local\permission_manager;
use local_remotesupport\output\student_status;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint the student's browser polls to refresh their assistance
 * request/session status without reloading request.php.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_student_status extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * @param int $courseid
     * @return array
     */
    public static function execute($courseid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        permission_manager::require_can_request($context);

        return student_status::export($params['courseid'], (int) $USER->id);
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'isnone' => new external_value(PARAM_BOOL, 'No open request'),
            'isrequested' => new external_value(PARAM_BOOL, 'Waiting for a teacher to accept'),
            'isaccepted' => new external_value(PARAM_BOOL, 'Accepted, not yet entered'),
            'isactive' => new external_value(PARAM_BOOL, 'Session active'),
            'sessionid' => new external_value(PARAM_INT, 'Session id, 0 if none'),
            'statusmessage' => new external_value(PARAM_RAW, 'Human readable status'),
            'supportavailable' => new external_value(
                PARAM_BOOL, 'Whether any teacher currently accepts requests', VALUE_OPTIONAL),
            'requestformurl' => new external_value(PARAM_URL, 'Request form action url', VALUE_OPTIONAL),
            'sesskey' => new external_value(PARAM_RAW, 'Session key for the request form', VALUE_OPTIONAL),
            'maxreasonlength' => new external_value(PARAM_INT, 'Max length of the optional reason field', VALUE_OPTIONAL),
            'cancelurl' => new external_value(PARAM_URL, 'Cancel action url', VALUE_OPTIONAL),
            'enterurl' => new external_value(PARAM_URL, 'Enter action url', VALUE_OPTIONAL),
            'finishurl' => new external_value(PARAM_URL, 'Finish action url', VALUE_OPTIONAL),
        ]);
    }
}
