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

namespace local_remotesupport;

use local_remotesupport\local\session_manager;
use local_remotesupport\local\token_manager;

/**
 * Tests for the session_manager state machine.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_manager_test extends \advanced_testcase {

    /**
     * Create a course with one student and one teacher enrolled.
     *
     * @return array [course, student, teacher]
     */
    private function setup_course_with_users(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        return [$course, $student, $teacher];
    }

    public function test_create_request(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $session = session_manager::create_request($course->id, $student->id);

        $this->assertSame(session_manager::STATUS_REQUESTED, $session->status);
        $this->assertEquals($student->id, $session->studentid);
        $this->assertNull($session->teacherid);
    }

    public function test_create_request_stores_reason(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $session = session_manager::create_request($course->id, $student->id, '  El vídeo no se reproduce  ');

        $this->assertSame('El vídeo no se reproduce', $session->reason);
    }

    public function test_create_request_defaults_reason_to_null_when_blank(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $session = session_manager::create_request($course->id, $student->id, '   ');

        $this->assertNull($session->reason);
    }

    public function test_create_request_without_reason_argument_defaults_to_null(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $session = session_manager::create_request($course->id, $student->id);

        $this->assertNull($session->reason);
    }

    public function test_create_request_truncates_long_reason(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $session = session_manager::create_request($course->id, $student->id, str_repeat('a', 500));

        $this->assertSame(session_manager::MAX_REASON_LENGTH, strlen($session->reason));
    }

    public function test_create_request_stores_returnurl(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $session = session_manager::create_request($course->id, $student->id, '', '/mod/forum/discuss.php?d=5');

        $this->assertSame('/mod/forum/discuss.php?d=5', $session->returnurl);
    }

    public function test_create_request_without_returnurl_argument_defaults_to_null(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $session = session_manager::create_request($course->id, $student->id);

        $this->assertNull($session->returnurl);
    }

    public function test_create_request_drops_oversized_returnurl_instead_of_truncating(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $session = session_manager::create_request(
            $course->id,
            $student->id,
            '',
            '/mod/forum/discuss.php?d=' . str_repeat('1', session_manager::MAX_RETURNURL_LENGTH)
        );

        $this->assertNull($session->returnurl);
    }

    public function test_duplicate_open_request_is_rejected(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        session_manager::create_request($course->id, $student->id);

        $this->expectException(\moodle_exception::class);
        session_manager::create_request($course->id, $student->id);
    }

    public function test_authorized_teacher_can_accept_request(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);

        $accepted = session_manager::accept_request($session->id, $teacher->id);

        $this->assertSame(session_manager::STATUS_ACCEPTED, $accepted->status);
        $this->assertEquals($teacher->id, $accepted->teacherid);
    }

    public function test_unauthorized_user_cannot_accept_request(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $session = session_manager::create_request($course->id, $student->id);

        $this->expectException(\required_capability_exception::class);
        session_manager::accept_request($session->id, $otherstudent->id);
    }

    public function test_teacher_from_unrelated_course_cannot_accept(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();
        $othercourse = $this->getDataGenerator()->create_course();
        $otherteacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $session = session_manager::create_request($course->id, $student->id);

        $this->expectException(\required_capability_exception::class);
        session_manager::accept_request($session->id, $otherteacher->id);
    }

    public function test_cannot_accept_already_accepted_request(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);

        $this->expectException(\moodle_exception::class);
        session_manager::accept_request($session->id, $teacher->id);
    }

    public function test_expired_request_cannot_be_accepted(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);

        $DB->set_field('local_remotesupport_session', 'timerequestexpires', time() - 1, ['id' => $session->id]);

        $this->expectException(\moodle_exception::class);
        session_manager::accept_request($session->id, $teacher->id);
    }

    public function test_scheduled_task_expires_stale_requests(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        $DB->set_field('local_remotesupport_session', 'timerequestexpires', time() - 1, ['id' => $session->id]);

        $count = session_manager::expire_stale_requests();

        $this->assertSame(1, $count);
        $updated = $DB->get_record('local_remotesupport_session', ['id' => $session->id]);
        $this->assertSame(session_manager::STATUS_EXPIRED, $updated->status);
    }

    public function test_student_can_cancel_own_request(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);

        $cancelled = session_manager::cancel_request($session->id, $student->id);

        $this->assertSame(session_manager::STATUS_CANCELLED, $cancelled->status);
    }

    public function test_other_student_cannot_cancel_request(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $session = session_manager::create_request($course->id, $student->id);

        $this->expectException(\moodle_exception::class);
        session_manager::cancel_request($session->id, $otherstudent->id);
    }

    public function test_cannot_cancel_accepted_request(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);

        $this->expectException(\moodle_exception::class);
        session_manager::cancel_request($session->id, $student->id);
    }

    public function test_entry_token_activates_session_on_first_use(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);

        $token = session_manager::issue_entry_token($session->id, $teacher->id);
        $entered = session_manager::enter_session($session->id, $teacher->id, $token);

        $this->assertSame(session_manager::STATUS_ACTIVE, $entered->status);
        $this->assertNotNull($entered->timestarted);
    }

    public function test_student_and_teacher_tokens_are_independent(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);

        $teachertoken = session_manager::issue_entry_token($session->id, $teacher->id);
        session_manager::enter_session($session->id, $teacher->id, $teachertoken);

        // Student issues their own token afterwards; it must not invalidate the teacher's.
        $studenttoken = session_manager::issue_entry_token($session->id, $student->id);
        session_manager::enter_session($session->id, $student->id, $studenttoken);

        // The teacher's original token must still work.
        $entered = session_manager::enter_session($session->id, $teacher->id, $teachertoken);
        $this->assertSame(session_manager::STATUS_ACTIVE, $entered->status);
    }

    public function test_invalid_token_is_rejected(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        session_manager::issue_entry_token($session->id, $teacher->id);

        $this->expectException(\moodle_exception::class);
        session_manager::enter_session($session->id, $teacher->id, token_manager::generate());
    }

    public function test_other_user_cannot_enter_session(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        $token = session_manager::issue_entry_token($session->id, $teacher->id);

        $this->expectException(\moodle_exception::class);
        session_manager::enter_session($session->id, $otherstudent->id, $token);
    }

    public function test_student_can_close_active_session(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        $token = session_manager::issue_entry_token($session->id, $teacher->id);
        session_manager::enter_session($session->id, $teacher->id, $token);

        $closed = session_manager::close_session($session->id, $student->id);

        $this->assertSame(session_manager::STATUS_CLOSED, $closed->status);
        $this->assertNotNull($closed->timeended);
    }

    public function test_teacher_can_close_active_session(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);

        $closed = session_manager::close_session($session->id, $teacher->id);

        $this->assertSame(session_manager::STATUS_CLOSED, $closed->status);
    }

    public function test_closed_session_cannot_be_reopened(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        session_manager::close_session($session->id, $teacher->id);

        $this->expectException(\moodle_exception::class);
        session_manager::close_session($session->id, $teacher->id);
    }

    public function test_manager_can_close_any_session(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);

        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = $this->getDataGenerator()->create_role();
        assign_capability('local/remotesupport:managesessions', CAP_ALLOW, $managerroleid, \context_system::instance());
        role_assign($managerroleid, $manager->id, \context_system::instance());

        $closed = session_manager::close_session($session->id, $manager->id);
        $this->assertSame(session_manager::STATUS_CLOSED, $closed->status);
    }

    public function test_get_open_request_for_student_global_returns_false_when_none(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $this->assertFalse(session_manager::get_open_request_for_student_global($student->id));
    }

    public function test_get_open_request_for_student_global_finds_request_in_any_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course1->id, 'student');
        $generator->enrol_user($student->id, $course2->id, 'student');

        $session = session_manager::create_request($course2->id, $student->id);

        $found = session_manager::get_open_request_for_student_global($student->id);
        $this->assertEquals($session->id, $found->id);
    }

    public function test_get_open_request_for_student_global_ignores_other_students(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        session_manager::create_request($course->id, $otherstudent->id);

        $this->assertFalse(session_manager::get_open_request_for_student_global($student->id));
    }

    public function test_get_open_request_for_student_global_ignores_closed_sessions(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        session_manager::close_session($session->id, $teacher->id);

        $this->assertFalse(session_manager::get_open_request_for_student_global($student->id));
    }

    public function test_pending_requests_only_listed_for_authorized_teacher(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $othercourse = $this->getDataGenerator()->create_course();
        $otherteacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');

        session_manager::create_request($course->id, $student->id);

        $this->assertCount(1, session_manager::get_pending_requests_for_teacher($teacher->id));
        $this->assertCount(0, session_manager::get_pending_requests_for_teacher($otherteacher->id));
    }

    /**
     * Runs a session through to 'closed', with a known 5-minute duration.
     *
     * @return array [session, course, student, teacher]
     */
    private function create_closed_session_with_duration(int $durationseconds): array {
        global $DB;
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        $token = session_manager::issue_entry_token($session->id, $teacher->id);
        $session = session_manager::enter_session($session->id, $teacher->id, $token);

        // Backdate timestarted so timeended - timestarted is a known value;
        // close_session() always sets timeended to the real current time.
        $DB->set_field('local_remotesupport_session', 'timestarted', time() - $durationseconds, ['id' => $session->id]);

        session_manager::close_session($session->id, $teacher->id);

        return [$session, $course, $student, $teacher];
    }

    public function test_closed_sessions_sql_scopes_to_teacher_and_closed_status(): void {
        global $DB;
        $this->resetAfterTest();
        [, , , $teacher] = $this->create_closed_session_with_duration(300);

        // A pending request for the same teacher must not show up: only
        // 'closed' sessions belong in the history.
        [$othercourse, $otherstudent] = $this->setup_course_with_users();
        session_manager::create_request($othercourse->id, $otherstudent->id);

        $sql = session_manager::get_closed_sessions_sql_for_teacher($teacher->id);
        $rows = $DB->get_records_sql("SELECT {$sql['fields']} FROM {$sql['from']} WHERE {$sql['where']}", $sql['params']);

        $this->assertCount(1, $rows);
    }

    public function test_closed_sessions_sql_computes_duration_and_names(): void {
        global $DB;
        $this->resetAfterTest();
        [, $course, $student, $teacher] = $this->create_closed_session_with_duration(300);

        $sql = session_manager::get_closed_sessions_sql_for_teacher($teacher->id);
        $rows = array_values($DB->get_records_sql("SELECT {$sql['fields']} FROM {$sql['from']} WHERE {$sql['where']}", $sql['params']));

        $this->assertCount(1, $rows);
        $this->assertSame($course->fullname, $rows[0]->coursefullname);
        $this->assertSame($student->firstname, $rows[0]->studentfirstname);
        $this->assertSame($student->lastname, $rows[0]->studentlastname);
        // Allow a couple of seconds of slack: close_session() sets timeended
        // to the real current time, not a fixed value.
        $this->assertGreaterThanOrEqual(299, $rows[0]->duration);
        $this->assertLessThanOrEqual(305, $rows[0]->duration);
    }

    public function test_closed_sessions_sql_excludes_other_teachers(): void {
        global $DB;
        $this->resetAfterTest();
        [, , , $teacher] = $this->create_closed_session_with_duration(60);
        $otherteacher = $this->getDataGenerator()->create_user();

        $sql = session_manager::get_closed_sessions_sql_for_teacher($otherteacher->id);
        $rows = $DB->get_records_sql("SELECT {$sql['fields']} FROM {$sql['from']} WHERE {$sql['where']}", $sql['params']);

        $this->assertCount(0, $rows);
    }

    public function test_delete_sessions_removes_the_session_and_its_recording(): void {
        global $DB;
        $this->resetAfterTest();
        [$session, , , $teacher] = $this->create_closed_session_with_duration(60);
        \local_remotesupport\local\track_manager::record($session->id, $teacher->id, 'page', '{}');
        \local_remotesupport\local\event_manager::record_event($session->id, $teacher->id, 'chat_message', ['message' => 'hola']);

        $deleted = session_manager::delete_sessions([$session->id], $teacher->id);

        $this->assertSame(1, $deleted);
        $this->assertFalse($DB->record_exists('local_remotesupport_session', ['id' => $session->id]));
        $this->assertFalse($DB->record_exists('local_remotesupport_track', ['sessionid' => $session->id]));
        $this->assertFalse($DB->record_exists('local_remotesupport_event', ['sessionid' => $session->id]));
    }

    public function test_delete_sessions_rejects_a_different_teacher(): void {
        $this->resetAfterTest();
        [$session] = $this->create_closed_session_with_duration(60);
        $otherteacher = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        session_manager::delete_sessions([$session->id], $otherteacher->id);
    }

    public function test_delete_sessions_rejects_a_still_open_session(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);

        $this->expectException(\moodle_exception::class);
        session_manager::delete_sessions([$session->id], $teacher->id);
    }

    public function test_delete_sessions_allows_manager_override(): void {
        $this->resetAfterTest();
        [$session] = $this->create_closed_session_with_duration(60);

        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = $this->getDataGenerator()->create_role();
        assign_capability('local/remotesupport:managesessions', CAP_ALLOW, $managerroleid, \context_system::instance());
        role_assign($managerroleid, $manager->id, \context_system::instance());

        $deleted = session_manager::delete_sessions([$session->id], $manager->id);
        $this->assertSame(1, $deleted);
    }

    public function test_delete_sessions_is_all_or_nothing_across_a_batch(): void {
        global $DB;
        $this->resetAfterTest();
        [$ownsession, , , $teacher] = $this->create_closed_session_with_duration(60);
        [$othersession] = $this->create_closed_session_with_duration(60);

        // $teacher owns $ownsession but not $othersession — the whole batch
        // must be rejected, so even the session $teacher legitimately owns
        // must survive.
        try {
            session_manager::delete_sessions([$ownsession->id, $othersession->id], $teacher->id);
            $this->fail('Expected a moodle_exception to be thrown.');
        } catch (\moodle_exception $e) {
            // Expected.
        }

        $this->assertTrue($DB->record_exists('local_remotesupport_session', ['id' => $ownsession->id]));
        $this->assertTrue($DB->record_exists('local_remotesupport_session', ['id' => $othersession->id]));
    }
}
