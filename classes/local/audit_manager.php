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

namespace local_remotesupport\local;

use context;
use context_course;
use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * Records auditable events for the plugin via Moodle's standard event/log system.
 *
 * The plugin keeps no bespoke audit table: everything recorded here lands in
 * Moodle's own log store and inherits its retention policy.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_manager {

    /**
     * Record that a student created a request.
     *
     * @param \stdClass $session
     */
    public static function request_created(\stdClass $session): void {
        \local_remotesupport\event\request_created::create([
            'objectid' => $session->id,
            'context' => context_course::instance($session->courseid),
            'courseid' => $session->courseid,
            'relateduserid' => $session->studentid,
        ])->trigger();
    }

    /**
     * Record that a teacher accepted a request.
     *
     * @param \stdClass $session
     */
    public static function request_accepted(\stdClass $session): void {
        \local_remotesupport\event\request_accepted::create([
            'objectid' => $session->id,
            'context' => context_course::instance($session->courseid),
            'courseid' => $session->courseid,
            'relateduserid' => $session->studentid,
        ])->trigger();
    }

    /**
     * Record that a request/session ended without becoming a full session.
     *
     * @param \stdClass $session
     * @param string $reason One of 'cancelled', 'expired'.
     */
    public static function request_cancelled(\stdClass $session, string $reason): void {
        \local_remotesupport\event\request_cancelled::create([
            'objectid' => $session->id,
            'context' => context_course::instance($session->courseid),
            'courseid' => $session->courseid,
            'relateduserid' => $session->studentid,
            'other' => ['reason' => $reason],
        ])->trigger();
    }

    /**
     * Record that a session became active.
     *
     * @param \stdClass $session
     * @param int $actorid User who triggered the transition.
     */
    public static function session_started(\stdClass $session, int $actorid): void {
        \local_remotesupport\event\session_started::create([
            'objectid' => $session->id,
            'context' => context_course::instance($session->courseid),
            'courseid' => $session->courseid,
            'userid' => $actorid,
            'relateduserid' => $session->studentid,
        ])->trigger();
    }

    /**
     * Record that a session was closed.
     *
     * @param \stdClass $session
     * @param int $actorid User who closed the session.
     */
    public static function session_ended(\stdClass $session, int $actorid): void {
        \local_remotesupport\event\session_ended::create([
            'objectid' => $session->id,
            'context' => context_course::instance($session->courseid),
            'courseid' => $session->courseid,
            'userid' => $actorid,
            'relateduserid' => $session->studentid,
        ])->trigger();
    }

    /**
     * Record a relevant authorization failure.
     *
     * @param context $context
     * @param string $reason Short machine-readable reason, e.g. 'notowner', 'invalidtoken'.
     */
    public static function access_denied(context $context, string $reason): void {
        \local_remotesupport\event\access_denied::create([
            'context' => $context,
            'other' => ['reason' => $reason],
        ])->trigger();
    }

    /**
     * Record that the student changed the granted control level.
     *
     * @param \stdClass $session
     * @param string $from
     * @param string $to
     */
    public static function control_level_changed(\stdClass $session, string $from, string $to): void {
        \local_remotesupport\event\control_level_changed::create([
            'objectid' => $session->id,
            'context' => context_course::instance($session->courseid),
            'courseid' => $session->courseid,
            'userid' => $session->studentid,
            'other' => ['from' => $from, 'to' => $to],
        ])->trigger();
    }

    /**
     * Record the outcome of a teacher's remote click request.
     *
     * @param \stdClass $session
     * @param string $outcome One of 'clicked', 'blocked', 'declined', 'notfound'.
     */
    public static function remote_click(\stdClass $session, string $outcome): void {
        \local_remotesupport\event\remote_click::create([
            'objectid' => $session->id,
            'context' => context_course::instance($session->courseid),
            'courseid' => $session->courseid,
            'userid' => $session->studentid,
            'other' => ['outcome' => $outcome],
        ])->trigger();
    }

    /**
     * Record the outcome of a teacher's remote field-write request.
     *
     * The written value itself is never logged, only the outcome.
     *
     * @param \stdClass $session
     * @param string $outcome One of 'applied', 'blocked', 'notfound'.
     */
    public static function remote_input(\stdClass $session, string $outcome): void {
        \local_remotesupport\event\remote_input::create([
            'objectid' => $session->id,
            'context' => context_course::instance($session->courseid),
            'courseid' => $session->courseid,
            'userid' => $session->studentid,
            'other' => ['outcome' => $outcome],
        ])->trigger();
    }
}
