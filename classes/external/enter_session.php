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
 * AJAX endpoint the student's or teacher's browser calls, right when the
 * "Enter session" button is clicked, to obtain a fresh one-time entry token
 * and the resulting session.php url. Shared by request.php and view.php.
 *
 * Deliberately not pre-generated during status polling:
 * session_manager::issue_entry_token() invalidates any previously issued
 * token for that role on every call, so calling it on every poll tick would
 * make an already-open session.php tab's link go stale.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enter_session extends external_api {

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
