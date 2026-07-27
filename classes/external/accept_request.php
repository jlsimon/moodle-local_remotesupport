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
use local_remotesupport\local\session_manager;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint the teacher's browser calls to accept a pending request
 * without reloading view.php. Mirrors view.php's action=accept exactly:
 * accept, then immediately issue an entry token and hand back the
 * session.php url to navigate to — authorization (capability + course
 * ownership of the request) is enforced by
 * session_manager::accept_request() itself.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accept_request extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
        ]);
    }

    /**
     * @param int $sessionid
     * @return array
     */
    public static function execute($sessionid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'sessionid' => $sessionid,
        ]);

        $session = session_manager::get_session($params['sessionid']);
        self::validate_context(context_course::instance($session->courseid));

        $session = session_manager::accept_request($session->id, (int) $USER->id);
        $token = session_manager::issue_entry_token($session->id, (int) $USER->id);

        return [
            'redirecturl' => (new moodle_url('/local/remotesupport/session.php', [
                'id' => $session->id, 'token' => $token,
            ]))->out(false),
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'redirecturl' => new external_value(PARAM_URL, 'Url to navigate to in order to enter the session'),
        ]);
    }
}
