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
use context_system;
use local_remotesupport\local\permission_manager;
use local_remotesupport\local\session_manager;
use local_remotesupport\local\teacher_settings;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint the navbar badge module polls, on every Moodle page, for
 * any user able to provide assistance in at least one course. Deliberately
 * the cheapest possible read: just a count and the viewer's own
 * support-enabled flag, reusing the exact same query view.php's own list
 * already runs (session_manager::get_pending_requests_for_teacher()), so
 * the badge and the list it links to can never disagree.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_pending_count extends external_api {

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
        permission_manager::require_can_provide_anywhere((int) $USER->id);

        $pending = session_manager::get_pending_requests_for_teacher((int) $USER->id);

        return [
            'count' => count($pending),
            'supportenabled' => teacher_settings::is_support_enabled((int) $USER->id),
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Number of pending requests across the teacher\'s courses'),
            'supportenabled' => new external_value(PARAM_BOOL, 'Whether the viewer currently accepts requests'),
        ]);
    }
}
