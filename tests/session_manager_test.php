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
}
