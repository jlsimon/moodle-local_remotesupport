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
use local_remotesupport\local\permission_manager;
use local_remotesupport\local\session_manager;
use local_remotesupport\local\track_manager;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint the teacher's browser calls once, on loading a session
 * replay, to fetch its whole recorded track. Unlike pull_events, this is not
 * a live poll: a closed session's recording never grows, so the client
 * fetches it in one go and replays it locally.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_session_track extends external_api {

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
        permission_manager::require_can_replay_session($session, (int) $USER->id);

        if ($session->status !== session_manager::STATUS_CLOSED) {
            throw new moodle_exception('errorinvalidstatetransition', 'local_remotesupport');
        }

        $track = track_manager::get_track_for_session($session->id);

        return array_map(static fn ($event) => [
            'eventtype' => $event->eventtype,
            'payload' => $event->payload,
            'timecreated' => (int) $event->timecreated,
            'sourceuserid' => $event->sourceuserid !== null ? (int) $event->sourceuserid : 0,
        ], $track);
    }

    /**
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'eventtype' => new external_value(PARAM_ALPHAEXT, 'Event type'),
            'payload' => new external_value(PARAM_RAW, 'JSON-encoded event payload'),
            'timecreated' => new external_value(PARAM_INT, 'Unix timestamp'),
            'sourceuserid' => new external_value(
                PARAM_INT,
                'The user whose browser generated this event, or 0 if recorded before this column existed'
            ),
        ]));
    }
}
