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
use local_remotesupport\local\session_manager;
use local_remotesupport\local\teacher_settings;
use moodle_exception;

/**
 * AJAX endpoint the student's browser calls to create an assistance request
 * without reloading request.php.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_assistance extends external_api {
    /**
     * Parameter definition for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'reason' => new external_value(PARAM_TEXT, 'Optional reason', VALUE_DEFAULT, ''),
            'fromurl' => new external_value(
                PARAM_LOCALURL,
                'Local url of the page the student requested assistance from, to return to once the session starts',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Creates a new assistance request.
     *
     * @param int $courseid
     * @param string $reason
     * @param string $fromurl
     * @return array
     */
    public static function execute($courseid, $reason = '', $fromurl = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'reason' => $reason,
            'fromurl' => $fromurl,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        permission_manager::require_can_request($context);

        if (!teacher_settings::is_support_available_for_course($params['courseid'])) {
            throw new moodle_exception('errornosupportavailable', 'local_remotesupport');
        }

        $session = session_manager::create_request(
            $params['courseid'],
            (int) $USER->id,
            $params['reason'],
            $params['fromurl']
        );

        return ['sessionid' => (int) $session->id];
    }

    /**
     * Return definition for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sessionid' => new external_value(PARAM_INT, 'The new request id'),
        ]);
    }
}
