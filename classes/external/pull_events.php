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
use context_course;
use local_remotesupport\local\event_manager;
use local_remotesupport\local\session_manager;
use local_remotesupport\realtime\polling_transport;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint the teacher's browser calls to poll for new capture events.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pull_events extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'sinceid' => new external_value(PARAM_INT, 'Only return events with id greater than this'),
        ]);
    }

    /**
     * @param int $sessionid
     * @param int $sinceid
     * @return array
     */
    public static function execute($sessionid, $sinceid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'sessionid' => $sessionid,
            'sinceid' => $sinceid,
        ]);

        $session = session_manager::get_session($params['sessionid']);
        self::validate_context(context_course::instance($session->courseid));

        $transport = new polling_transport();
        $events = $transport->pull_events($session->id, (int) $USER->id, $params['sinceid'], event_manager::DEFAULT_PULL_LIMIT);

        return array_map(static fn ($event) => [
            'id' => (int) $event->id,
            'eventtype' => $event->eventtype,
            'payload' => json_encode($event->payload),
            'timecreated' => (int) $event->timecreated,
        ], $events);
    }

    /**
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Event id, usable as the next sinceid'),
            'eventtype' => new external_value(PARAM_ALPHAEXT, 'Event type'),
            'payload' => new external_value(PARAM_RAW, 'JSON-encoded event payload'),
            'timecreated' => new external_value(PARAM_INT, 'Unix timestamp'),
        ]));
    }
}
