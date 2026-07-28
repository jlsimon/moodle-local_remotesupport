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

use context_course;
use core_text;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Owns the local_remotesupport_session table and its state machine.
 *
 * States: requested -> accepted -> active -> closed
 *                   \-> cancelled   \-> expired (only from requested)
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_manager {

    const STATUS_REQUESTED = 'requested';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    /** @var string[] Statuses that mean "not finished yet". */
    const OPEN_STATUSES = [self::STATUS_REQUESTED, self::STATUS_ACCEPTED, self::STATUS_ACTIVE];

    /** @var int Maximum stored length of the student's optional request reason. */
    const MAX_REASON_LENGTH = 255;

    /**
     * Create a new assistance request for a student in a course.
     *
     * Refuses to create a second open request for the same student/course,
     * per the requirement that equivalent active requests are controlled.
     *
     * @param int $courseid
     * @param int $studentid
     * @param string $reason Optional free text the student gives the teacher; blank means none.
     * @return \stdClass The new session row.
     * @throws moodle_exception
     */
    public static function create_request(int $courseid, int $studentid, string $reason = ''): \stdClass {
        global $DB;

        if (self::get_open_request_for_student($studentid, $courseid)) {
            throw new moodle_exception('errorrequestexists', 'local_remotesupport');
        }

        $reason = trim($reason);
        if (core_text::strlen($reason) > self::MAX_REASON_LENGTH) {
            $reason = core_text::substr($reason, 0, self::MAX_REASON_LENGTH);
        }

        $now = time();
        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->contextid = context_course::instance($courseid)->id;
        $record->studentid = $studentid;
        $record->teacherid = null;
        $record->status = self::STATUS_REQUESTED;
        $record->tokenhashstudent = null;
        $record->tokenhashteacher = null;
        $record->reason = $reason !== '' ? $reason : null;
        $record->timecreated = $now;
        $record->timerequestexpires = $now + self::get_request_expiry_seconds();
        $record->timestarted = null;
        $record->timeended = null;
        $record->timemodified = $now;
        $record->id = $DB->insert_record('local_remotesupport_session', $record);

        audit_manager::request_created($record);

        return $record;
    }

    /**
     * Return the student's current open (non-terminal) request/session, if any.
     *
     * @param int $studentid
     * @param int|null $courseid Restrict to this course, or null to look across all courses.
     * @return \stdClass|false
     */
    public static function get_open_request_for_student(int $studentid, ?int $courseid = null) {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(self::OPEN_STATUSES, SQL_PARAMS_NAMED);
        $params['studentid'] = $studentid;

        $coursesql = '';
        if ($courseid !== null) {
            $coursesql = 'AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = "SELECT * FROM {local_remotesupport_session}
                 WHERE studentid = :studentid {$coursesql} AND status {$insql}
                 ORDER BY timecreated DESC";
        $records = $DB->get_records_sql($sql, $params, 0, 1);

        return $records ? reset($records) : false;
    }

    /**
     * Return the student's current open (non-terminal) request/session in
     * any course, if any. Thin alias over get_open_request_for_student()
     * with no course filter, kept as its own name because its one call
     * site — the site-wide floating request button (lib.php) — has no
     * course context to search from, and "global" makes that obvious
     * without reading the implementation.
     *
     * @param int $studentid
     * @return \stdClass|false
     */
    public static function get_open_request_for_student_global(int $studentid) {
        return self::get_open_request_for_student($studentid);
    }

    /**
     * Return the active session where the given user is the student, if any.
     *
     * Single indexed lookup, meant to be called on every page load (from
     * lib.php's before_footer hook) to decide whether to inject the capture
     * script, so it deliberately does no capability checks of its own.
     *
     * @param int $studentid
     * @return \stdClass|false
     */
    public static function get_active_session_for_student(int $studentid) {
        global $DB;
        return $DB->get_record('local_remotesupport_session', [
            'studentid' => $studentid,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Return pending (status=requested, not expired) requests in courses where
     * the given teacher holds the provideassistance capability.
     *
     * @param int $teacherid
     * @return \stdClass[]
     */
    public static function get_pending_requests_for_teacher(int $teacherid): array {
        global $DB;

        $courses = get_user_capability_course('local/remotesupport:provideassistance', $teacherid, false);
        if (!$courses) {
            return [];
        }
        $courseids = array_map(fn($c) => $c->id, $courses);

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['status'] = self::STATUS_REQUESTED;
        $params['now'] = time();

        $sql = "SELECT * FROM {local_remotesupport_session}
                 WHERE status = :status AND timerequestexpires > :now AND courseid {$insql}
                 ORDER BY timecreated ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Return open sessions (accepted or active) where the given teacher is assigned.
     *
     * @param int $teacherid
     * @return \stdClass[]
     */
    public static function get_open_sessions_for_teacher(int $teacherid): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(self::OPEN_STATUSES, SQL_PARAMS_NAMED);
        $params['teacherid'] = $teacherid;

        return $DB->get_records_select(
            'local_remotesupport_session',
            "teacherid = :teacherid AND status {$insql}",
            $params,
            'timemodified DESC'
        );
    }

    /**
     * Fetch a session by id.
     *
     * @param int $sessionid
     * @return \stdClass
     * @throws moodle_exception If not found.
     */
    public static function get_session(int $sessionid): \stdClass {
        global $DB;
        $session = $DB->get_record('local_remotesupport_session', ['id' => $sessionid]);
        if (!$session) {
            throw new moodle_exception('errorsessionnotfound', 'local_remotesupport');
        }
        return $session;
    }

    /**
     * Accept a pending request.
     *
     * @param int $sessionid
     * @param int $teacherid
     * @return \stdClass Updated session row.
     * @throws moodle_exception
     */
    public static function accept_request(int $sessionid, int $teacherid): \stdClass {
        global $DB;

        $session = self::get_session($sessionid);

        if ($session->status !== self::STATUS_REQUESTED) {
            throw new moodle_exception('errorinvalidstatetransition', 'local_remotesupport');
        }
        if ($session->timerequestexpires <= time()) {
            throw new moodle_exception('errorrequestexpired', 'local_remotesupport');
        }

        permission_manager::require_can_provide(context_course::instance($session->courseid), $teacherid);

        $session->teacherid = $teacherid;
        $session->status = self::STATUS_ACCEPTED;
        $session->timemodified = time();
        $DB->update_record('local_remotesupport_session', $session);

        audit_manager::request_accepted($session);

        return $session;
    }

    /**
     * Cancel a pending request. Only the requesting student may cancel.
     *
     * @param int $sessionid
     * @param int $studentid
     * @return \stdClass Updated session row.
     * @throws moodle_exception
     */
    public static function cancel_request(int $sessionid, int $studentid): \stdClass {
        global $DB;

        $session = self::get_session($sessionid);

        if ($session->studentid != $studentid) {
            audit_manager::access_denied(context_course::instance($session->courseid), 'notowner');
            throw new moodle_exception('errornopermission', 'local_remotesupport');
        }
        if ($session->status !== self::STATUS_REQUESTED) {
            throw new moodle_exception('errorinvalidstatetransition', 'local_remotesupport');
        }

        $session->status = self::STATUS_CANCELLED;
        $session->timemodified = time();
        $DB->update_record('local_remotesupport_session', $session);

        audit_manager::request_cancelled($session, self::STATUS_CANCELLED);

        return $session;
    }

    /**
     * Issue a fresh one-time entry token for an accepted or active session.
     *
     * Each call invalidates any previously issued token for this session.
     *
     * @param int $sessionid
     * @param int $userid Must be the session's student or teacher.
     * @return string Plaintext token, to be embedded in a single redirect URL.
     * @throws moodle_exception
     */
    public static function issue_entry_token(int $sessionid, int $userid): string {
        global $DB;

        $session = self::get_session($sessionid);
        $field = self::entry_token_field($session, $userid);

        if (!in_array($session->status, [self::STATUS_ACCEPTED, self::STATUS_ACTIVE], true)) {
            throw new moodle_exception('errorinvalidstatetransition', 'local_remotesupport');
        }

        $token = token_manager::generate();
        $session->$field = token_manager::hash($token);
        $session->timemodified = time();
        $DB->update_record('local_remotesupport_session', $session);

        return $token;
    }

    /**
     * Enter a session using a token issued by issue_entry_token().
     *
     * On first entry (status accepted) this activates the session.
     *
     * @param int $sessionid
     * @param int $userid
     * @param string $token
     * @return \stdClass Session row.
     * @throws moodle_exception
     */
    public static function enter_session(int $sessionid, int $userid, string $token): \stdClass {
        global $DB;

        $session = self::get_session($sessionid);
        $field = self::entry_token_field($session, $userid);

        if (!in_array($session->status, [self::STATUS_ACCEPTED, self::STATUS_ACTIVE], true)) {
            throw new moodle_exception('errorinvalidstatetransition', 'local_remotesupport');
        }
        if (!$session->$field || !token_manager::verify($token, $session->$field)) {
            audit_manager::access_denied(context_course::instance($session->courseid), 'invalidtoken');
            throw new moodle_exception('errorinvalidtoken', 'local_remotesupport');
        }

        if ($session->status === self::STATUS_ACCEPTED) {
            $session->status = self::STATUS_ACTIVE;
            $session->timestarted = time();
            $session->timemodified = time();
            $DB->update_record('local_remotesupport_session', $session);
            audit_manager::session_started($session, $userid);
        }

        return $session;
    }

    /**
     * Determine which per-role token column applies to a user entering a session.
     *
     * Only the student and the assigned teacher can hold entry tokens; a
     * manager overseeing sessions administratively (close, cancel) does not
     * "enter" one in Phase 1, since there is no observer UI yet.
     *
     * @param \stdClass $session
     * @param int $userid
     * @return string Field name: 'tokenhashstudent' or 'tokenhashteacher'.
     * @throws moodle_exception
     */
    private static function entry_token_field(\stdClass $session, int $userid): string {
        if ($session->studentid == $userid) {
            return 'tokenhashstudent';
        }
        if ($session->teacherid == $userid) {
            return 'tokenhashteacher';
        }
        audit_manager::access_denied(context_course::instance($session->courseid), 'notowner');
        throw new moodle_exception('errornopermission', 'local_remotesupport');
    }

    /**
     * Close an accepted or active session. Either party (or a manager) may close it.
     *
     * @param int $sessionid
     * @param int $userid
     * @return \stdClass Updated session row.
     * @throws moodle_exception
     */
    public static function close_session(int $sessionid, int $userid): \stdClass {
        global $DB;

        $session = self::get_session($sessionid);
        permission_manager::require_owner_or_manage($session, $userid);

        if (!in_array($session->status, [self::STATUS_ACCEPTED, self::STATUS_ACTIVE], true)) {
            throw new moodle_exception('errorinvalidstatetransition', 'local_remotesupport');
        }

        $session->status = self::STATUS_CLOSED;
        $session->timeended = time();
        $session->timemodified = time();
        $DB->update_record('local_remotesupport_session', $session);

        event_manager::purge_session_events($session->id);
        audit_manager::session_ended($session, $userid);

        return $session;
    }

    /**
     * Expire pending requests whose deadline has passed. Intended to be called
     * from the scheduled task only.
     *
     * @return int Number of requests expired.
     */
    public static function expire_stale_requests(): int {
        global $DB;

        $stale = $DB->get_records_select(
            'local_remotesupport_session',
            'status = :status AND timerequestexpires <= :now',
            ['status' => self::STATUS_REQUESTED, 'now' => time()]
        );

        foreach ($stale as $session) {
            $session->status = self::STATUS_EXPIRED;
            $session->timemodified = time();
            $DB->update_record('local_remotesupport_session', $session);
            audit_manager::request_cancelled($session, self::STATUS_EXPIRED);
        }

        return count($stale);
    }

    /**
     * How long a request stays valid before expiring, from plugin settings.
     *
     * @return int Seconds.
     */
    private static function get_request_expiry_seconds(): int {
        $seconds = (int) get_config('local_remotesupport', 'requestexpiryseconds');
        return max($seconds, MINSECS);
    }
}
