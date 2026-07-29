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

use core_external\external_api;
use local_remotesupport\external\accept_request;
use local_remotesupport\external\cancel_request;
use local_remotesupport\external\enter_session;
use local_remotesupport\external\finish_session;
use local_remotesupport\external\get_pending_count;
use local_remotesupport\external\get_session_track;
use local_remotesupport\external\get_student_status;
use local_remotesupport\external\get_teacher_dashboard;
use local_remotesupport\external\pull_events;
use local_remotesupport\external\push_event;
use local_remotesupport\external\request_assistance;
use local_remotesupport\local\session_manager;

/**
 * End-to-end tests for the AJAX external API classes, exercising the same
 * parameter and context validation a real web service call goes through.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_api_test extends \advanced_testcase {

    /**
     * @return array [session, student, teacher]
     */
    private function setup_active_session(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        $token = session_manager::issue_entry_token($session->id, $teacher->id);
        $session = session_manager::enter_session($session->id, $teacher->id, $token);

        return [$session, $student, $teacher];
    }

    /**
     * @return array [course, student, teacher], no request created yet.
     */
    private function setup_course_with_users(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        return [$course, $student, $teacher];
    }

    /**
     * Validates a value returned by execute() against the same external
     * function's own execute_returns() schema — the exact validation
     * external_api::call_external_function() performs for a real AJAX
     * request but calling ::execute() directly (as every test in this file
     * does) skips. A field present in the real data but missing from the
     * declared schema, or vice versa, throws here just like it would for a
     * real browser request; a mismatch found this way found a real bug
     * (get_teacher_dashboard's rows were missing accepturl/enterurl/
     * finishurl in its schema versus what the exporter actually returns).
     *
     * @param string $externalclassname Fully qualified external_api subclass.
     * @param array $result The value returned by that class's execute().
     */
    private function assert_valid_return(string $externalclassname, array $result): void {
        external_api::clean_returnvalue($externalclassname::execute_returns(), $result);
        // No assertion needed beyond "did not throw" — clean_returnvalue()
        // itself throws invalid_response_exception on any mismatch.
        $this->assertTrue(true);
    }

    public function test_push_event_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session();

        $this->setUser($student);
        $result = push_event::execute($session->id, 'page', json_encode(['url' => '/a', 'title' => 't', 'html' => '<p>hi</p>']));

        $this->assertArrayHasKey('id', $result);
        $this->assertGreaterThan(0, $result['id']);
    }

    public function test_push_event_rejects_non_json_payload(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session();

        $this->setUser($student);
        $this->expectException(\invalid_parameter_exception::class);
        push_event::execute($session->id, 'page', 'not-json');
    }

    public function test_push_event_rejects_wrong_user(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session();

        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        push_event::execute($session->id, 'page', json_encode(['url' => '/a']));
    }

    public function test_pull_events_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();

        $this->setUser($student);
        push_event::execute($session->id, 'scroll', json_encode(['x' => 1, 'y' => 2]));

        $this->setUser($teacher);
        $result = pull_events::execute($session->id, 0);
        $this->assert_valid_return(pull_events::class, $result);

        $this->assertCount(1, $result);
        $this->assertSame('scroll', $result[0]['eventtype']);
        $this->assertSame((int) $student->id, $result[0]['sourceuserid']);
        $decoded = json_decode($result[0]['payload'], true);
        $this->assertSame(1, $decoded['x']);
    }

    public function test_chat_message_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();

        $this->setUser($student);
        push_event::execute($session->id, 'chat_message', json_encode(['message' => 'hola']));

        $this->setUser($teacher);
        push_event::execute($session->id, 'chat_message', json_encode(['message' => 'hola tambien']));

        // Both sides see the full conversation, including their own messages
        // — see event_manager::get_events_since().
        $this->setUser($student);
        $studentview = pull_events::execute($session->id, 0);
        $this->assert_valid_return(pull_events::class, $studentview);

        $this->setUser($teacher);
        $teacherview = pull_events::execute($session->id, 0);
        $this->assert_valid_return(pull_events::class, $teacherview);

        $this->assertCount(2, $studentview);
        $this->assertCount(2, $teacherview);
        $this->assertSame((int) $student->id, $studentview[0]['sourceuserid']);
        $this->assertSame((int) $teacher->id, $studentview[1]['sourceuserid']);
    }

    public function test_pull_events_rejects_unrelated_user(): void {
        $this->resetAfterTest();
        [$session] = $this->setup_active_session();
        $stranger = $this->getDataGenerator()->create_user();

        $this->setUser($stranger);
        $this->expectException(\moodle_exception::class);
        pull_events::execute($session->id, 0);
    }

    public function test_resync_request_end_to_end(): void {
        // Regression test: 'resync_request' contains an underscore, which
        // PARAM_ALPHA (used for the eventtype parameter until this was
        // caught) rejects outright. This must go through the real
        // external API validation, not call polling_transport directly,
        // or it would not have caught the bug in the first place.
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();

        $this->setUser($teacher);
        $pushresult = push_event::execute($session->id, 'resync_request', json_encode([]));
        $this->assertGreaterThan(0, $pushresult['id']);

        $this->setUser($student);
        $result = pull_events::execute($session->id, 0);

        $this->assertCount(1, $result);
        $this->assertSame('resync_request', $result[0]['eventtype']);
    }

    public function test_get_student_status_none_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $this->setUser($student);
        $result = get_student_status::execute($course->id);
        $this->assert_valid_return(get_student_status::class, $result);

        $this->assertTrue($result['isnone']);
        $this->assertSame(0, $result['sessionid']);
        $this->assertTrue($result['supportavailable']);
    }

    public function test_get_student_status_rejects_user_without_capability_end_to_end(): void {
        $this->resetAfterTest();
        [$course] = $this->setup_course_with_users();
        $stranger = $this->getDataGenerator()->create_user();

        // Not enrolled at all, so validate_context()'s own require_login()
        // check rejects it before the requestassistance capability is even
        // reached — still the correct outcome (access denied), just a
        // different exception than an enrolled-but-uncapable user would get.
        $this->setUser($stranger);
        $this->expectException(\moodle_exception::class);
        get_student_status::execute($course->id);
    }

    public function test_request_assistance_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $this->setUser($student);
        $result = request_assistance::execute($course->id, 'necesito ayuda');
        $this->assert_valid_return(request_assistance::class, $result);

        $this->assertGreaterThan(0, $result['sessionid']);

        $status = get_student_status::execute($course->id);
        $this->assert_valid_return(get_student_status::class, $status);
        $this->assertTrue($status['isrequested']);
    }

    public function test_request_assistance_stores_fromurl_as_returnurl(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $this->setUser($student);
        $result = request_assistance::execute($course->id, '', '/mod/forum/discuss.php?d=5');
        $this->assert_valid_return(request_assistance::class, $result);

        $session = session_manager::get_session($result['sessionid']);
        $this->assertSame('/mod/forum/discuss.php?d=5', $session->returnurl);
    }

    public function test_request_assistance_rejects_second_open_request_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $this->setUser($student);
        request_assistance::execute($course->id);

        $this->expectException(\moodle_exception::class);
        request_assistance::execute($course->id);
    }

    public function test_cancel_request_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();

        $this->setUser($student);
        $created = request_assistance::execute($course->id);
        $result = cancel_request::execute($created['sessionid']);
        $this->assert_valid_return(cancel_request::class, $result);

        $this->assertSame(session_manager::STATUS_CANCELLED, $result['status']);
    }

    public function test_cancel_request_rejects_other_student_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($student);
        $created = request_assistance::execute($course->id);

        $this->setUser($otherstudent);
        $this->expectException(\moodle_exception::class);
        cancel_request::execute($created['sessionid']);
    }

    public function test_enter_session_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);

        $this->setUser($student);
        $result = enter_session::execute($session->id);
        $this->assert_valid_return(enter_session::class, $result);

        $this->assertStringContainsString('session.php', $result['redirecturl']);
        $this->assertStringContainsString((string) $session->id, $result['redirecturl']);
    }

    public function test_enter_session_rejects_unrelated_user_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        $stranger = $this->getDataGenerator()->create_user();

        $this->setUser($stranger);
        $this->expectException(\moodle_exception::class);
        enter_session::execute($session->id);
    }

    public function test_finish_session_by_student_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session();

        $this->setUser($student);
        $result = finish_session::execute($session->id);
        $this->assert_valid_return(finish_session::class, $result);

        $this->assertSame(session_manager::STATUS_CLOSED, $result['status']);
    }

    public function test_finish_session_by_teacher_end_to_end(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session();

        $this->setUser($teacher);
        $result = finish_session::execute($session->id);
        $this->assert_valid_return(finish_session::class, $result);

        $this->assertSame(session_manager::STATUS_CLOSED, $result['status']);
    }

    public function test_finish_session_rejects_unrelated_user_end_to_end(): void {
        $this->resetAfterTest();
        [$session] = $this->setup_active_session();
        $stranger = $this->getDataGenerator()->create_user();

        $this->setUser($stranger);
        $this->expectException(\moodle_exception::class);
        finish_session::execute($session->id);
    }

    public function test_accept_request_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);

        $this->setUser($teacher);
        $result = accept_request::execute($session->id);
        $this->assert_valid_return(accept_request::class, $result);

        $this->assertStringContainsString('session.php', $result['redirecturl']);
    }

    public function test_accept_request_rejects_teacher_without_capability_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);
        $otherteacher = $this->getDataGenerator()->create_and_enrol(
            $this->getDataGenerator()->create_course(), 'editingteacher');

        $this->setUser($otherteacher);
        $this->expectException(\moodle_exception::class);
        accept_request::execute($session->id);
    }

    public function test_get_teacher_dashboard_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        $session = session_manager::create_request($course->id, $student->id);

        $this->setUser($teacher);
        $result = get_teacher_dashboard::execute();
        $this->assert_valid_return(get_teacher_dashboard::class, $result);

        $this->assertTrue($result['haspending']);
        $this->assertCount(1, $result['pending']);
        $this->assertSame(fullname($student), $result['pending'][0]['studentname']);
        // Regression check: the AJAX response must carry a real, usable
        // accepturl, not just the row data — see docs/decisions.md, this
        // was missing here (though present in the PHP-rendered page) and
        // made "Aceptar" a no-op once the JS re-rendered the table.
        $this->assertStringContainsString('action=accept', $result['pending'][0]['accepturl']);
        $this->assertStringContainsString((string) $session->id, $result['pending'][0]['accepturl']);

        session_manager::accept_request($session->id, $teacher->id);
        $result = get_teacher_dashboard::execute();
        $this->assert_valid_return(get_teacher_dashboard::class, $result);
        $this->assertTrue($result['hasopen']);
        $this->assertStringContainsString('action=enter', $result['open'][0]['enterurl']);
        $this->assertStringContainsString('action=finish', $result['open'][0]['finishurl']);

        // The teacher in this test has viewsessionhistory (default archetype
        // grant), so the link to sessionhistory.php should be offered too.
        $this->assertTrue($result['hashistory']);
        $this->assertStringContainsString('sessionhistory.php', $result['historyurl']);
    }

    public function test_get_teacher_dashboard_rejects_user_without_capability_end_to_end(): void {
        $this->resetAfterTest();
        $stranger = $this->getDataGenerator()->create_user();

        $this->setUser($stranger);
        $this->expectException(\moodle_exception::class);
        get_teacher_dashboard::execute();
    }

    public function test_get_pending_count_end_to_end(): void {
        $this->resetAfterTest();
        [$course, $student, $teacher] = $this->setup_course_with_users();
        session_manager::create_request($course->id, $student->id);

        $this->setUser($teacher);
        $result = get_pending_count::execute();
        $this->assert_valid_return(get_pending_count::class, $result);

        $this->assertSame(1, $result['count']);
        $this->assertTrue($result['supportenabled']);
    }

    public function test_get_pending_count_rejects_user_without_capability_anywhere_end_to_end(): void {
        $this->resetAfterTest();
        $stranger = $this->getDataGenerator()->create_user();

        $this->setUser($stranger);
        $this->expectException(\moodle_exception::class);
        get_pending_count::execute();
    }

    public function test_get_session_track_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();

        $this->setUser($student);
        push_event::execute($session->id, 'page', json_encode(['url' => '/a', 'title' => 't', 'html' => '<p>hi</p>']));
        push_event::execute($session->id, 'chat_message', json_encode(['message' => 'hola']));

        $this->setUser($teacher);
        push_event::execute($session->id, 'chat_message', json_encode(['message' => 'hola tambien']));
        finish_session::execute($session->id);

        $result = get_session_track::execute($session->id);
        $this->assert_valid_return(get_session_track::class, $result);

        $this->assertCount(3, $result);
        $this->assertSame('page', $result[0]['eventtype']);
        $this->assertSame('chat_message', $result[1]['eventtype']);
        $this->assertSame((int) $student->id, $result[1]['sourceuserid']);
        $this->assertSame((int) $teacher->id, $result[2]['sourceuserid']);
    }

    public function test_get_session_track_rejects_the_student(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        $this->setUser($teacher);
        finish_session::execute($session->id);

        $this->setUser($student);
        $this->expectException(\moodle_exception::class);
        get_session_track::execute($session->id);
    }

    public function test_get_session_track_rejects_unrelated_teacher(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session();
        $othercourse = $this->getDataGenerator()->create_course();
        $otherteacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');

        $this->setUser($teacher);
        finish_session::execute($session->id);

        $this->setUser($otherteacher);
        $this->expectException(\moodle_exception::class);
        get_session_track::execute($session->id);
    }

    public function test_get_session_track_rejects_session_not_yet_closed(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session();

        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        get_session_track::execute($session->id);
    }
}
