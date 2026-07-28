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

namespace local_remotesupport\realtime;

use local_remotesupport\local\audit_manager;
use local_remotesupport\local\event_manager;
use local_remotesupport\local\session_manager;
use local_remotesupport\local\track_manager;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Simple AJAX-polling implementation of transport_interface.
 *
 * Which event types a participant may push is role-based: the student
 * pushes page/scroll (the teacher watches), the teacher pushes only
 * resync_request (asking the student for a fresh full snapshot). Both
 * roles may push chat_message — it is the one bidirectional type, see
 * event_manager::get_events_since(). Each side only ever pulls events
 * sourced by the *other* participant, except chat_message, which both
 * need to see in full (including their own messages).
 *
 * Also feeds track_manager: every successfully stored page/scroll event
 * is additionally, permanently recorded for later playback (see
 * track_manager's own doc comment for why this is a deliberate exception
 * to the plugin's usual ephemeral-by-default policy).
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class polling_transport implements transport_interface {

    /** @var array<string,string[]> Event types each role may push. */
    const ROLE_EVENT_TYPES = [
        'student' => ['page', 'scroll', 'chat_message'],
        'teacher' => ['resync_request', 'chat_message'],
    ];

    public function push_event(int $sessionid, int $userid, string $eventtype, array $payload): ?\stdClass {
        $session = session_manager::get_session($sessionid);
        $role = $this->role_of($session, $userid);

        if (!in_array($eventtype, self::ROLE_EVENT_TYPES[$role], true)) {
            audit_manager::access_denied(\context_course::instance($session->courseid), 'wrongrole');
            throw new moodle_exception('errornopermission', 'local_remotesupport');
        }
        if ($session->status !== session_manager::STATUS_ACTIVE) {
            throw new moodle_exception('errorsessionnotactive', 'local_remotesupport');
        }

        $event = event_manager::record_event($sessionid, $userid, $eventtype, $payload);

        if ($event !== null && in_array($eventtype, track_manager::TRACKED_EVENT_TYPES, true)) {
            track_manager::record($sessionid, $eventtype, $event->payload);
        }

        return $event;
    }

    public function pull_events(int $sessionid, int $userid, int $sinceid, int $limit = event_manager::DEFAULT_PULL_LIMIT): array {
        $session = session_manager::get_session($sessionid);
        $this->role_of($session, $userid);

        if ($session->status !== session_manager::STATUS_ACTIVE) {
            throw new moodle_exception('errorsessionnotactive', 'local_remotesupport');
        }

        return event_manager::get_events_since($sessionid, $sinceid, $userid, $limit);
    }

    /**
     * Determine whether $userid is the session's student or teacher.
     *
     * @param \stdClass $session
     * @param int $userid
     * @return string 'student' or 'teacher'.
     * @throws moodle_exception If $userid is neither.
     */
    private function role_of(\stdClass $session, int $userid): string {
        if ((int) $session->studentid === $userid) {
            return 'student';
        }
        if ((int) $session->teacherid === $userid) {
            return 'teacher';
        }
        audit_manager::access_denied(\context_course::instance($session->courseid), 'notowner');
        throw new moodle_exception('errornopermission', 'local_remotesupport');
    }
}
