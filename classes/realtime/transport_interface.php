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

/**
 * Delivers screen-reconstruction events between the student's and the
 * teacher's browser for an active session.
 *
 * Deliberately scoped to just event delivery, not general session
 * lifecycle (that stays in session_manager): the goal of this interface
 * is that a future WebSocket-based transport can replace
 * polling_transport without any caller (the external API classes, the
 * AMD modules) needing to change.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface transport_interface {
    /**
     * Validate and record an event pushed by a session participant.
     *
     * Which event types a given participant may push is role-based (see
     * polling_transport::ROLE_EVENT_TYPES); pushing a type your role
     * doesn't own is rejected the same as not belonging to the session.
     *
     * @param int $sessionid
     * @param int $userid The user pushing the event.
     * @param string $eventtype One of the whitelisted event types.
     * @param array $payload
     * @return \stdClass|null The stored event row, or null if a rate limit silently dropped it.
     */
    public function push_event(int $sessionid, int $userid, string $eventtype, array $payload): ?\stdClass;

    /**
     * Fetch events for a session newer than $sinceid, sourced by the
     * *other* participant (never the caller's own events).
     *
     * @param int $sessionid
     * @param int $userid The user pulling events; must be the session's student or teacher.
     * @param int $sinceid Cursor: only events with id greater than this are returned.
     * @param int $limit Maximum number of events to return.
     * @return \stdClass[] Ordered oldest-first.
     */
    public function pull_events(int $sessionid, int $userid, int $sinceid, int $limit): array;
}
