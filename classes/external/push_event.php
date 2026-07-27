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
use invalid_parameter_exception;
use local_remotesupport\local\session_manager;
use local_remotesupport\realtime\polling_transport;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint the student's browser calls to push a capture event.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class push_event extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'eventtype' => new external_value(PARAM_ALPHAEXT, 'Event type, e.g. page, scroll, cursor, highlight, resync_request'),
            'payload' => new external_value(PARAM_RAW, 'JSON-encoded event payload'),
        ]);
    }

    /**
     * @param int $sessionid
     * @param string $eventtype
     * @param string $payload
     * @return array
     */
    public static function execute($sessionid, $eventtype, $payload): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'sessionid' => $sessionid,
            'eventtype' => $eventtype,
            'payload' => $payload,
        ]);

        $session = session_manager::get_session($params['sessionid']);
        self::validate_context(context_course::instance($session->courseid));

        $decoded = json_decode($params['payload'], true);
        if (!is_array($decoded)) {
            throw new invalid_parameter_exception('payload must be a JSON object');
        }

        $transport = new polling_transport();
        $event = $transport->push_event($session->id, (int) $USER->id, $params['eventtype'], $decoded);

        // A null result means rate_limiter dropped it; that is not an error.
        return ['id' => $event ? (int) $event->id : 0];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Id of the stored event'),
        ]);
    }
}
