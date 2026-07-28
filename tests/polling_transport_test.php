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
use local_remotesupport\realtime\polling_transport;

/**
 * Tests for polling_transport: role-based push authorization (the student
 * pushes page/scroll, the teacher only pushes resync_request; both push
 * chat_message) and the "pull events sourced by the other participant" rule
 * — except chat_message, which both roles must see in full — all
 * independent of the AJAX layer.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class polling_transport_test extends \advanced_testcase {

    /**
     * Create a course with an active session between a student and a teacher.
     *
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

    public function test_student_can_push_page_and_scroll(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session();

        $transport = new polling_transport();
        $page = $transport->push_event($session->id, $student->id, 'page', ['url' => '/a', 'title' => 't', 'html' => '<p>a</p>']);
        $scroll = $transport->push_event($session->id, $student->id, 'scroll', ['x' => 1, 'y' => 1]);

        $this->assertSame('page', $page->eventtype);
        $this->assertSame('scroll', $scroll->eventtype);
    }

    public function test_teacher_can_push_resync_request_and_student_receives_it(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();

        $transport = new polling_transport();
        $transport->push_event($session->id, $teacher->id, 'resync_request', []);

        $events = $transport->pull_events($session->id, $student->id, 0, 20);

        $this->assertCount(1, $events);
        $this->assertSame('resync_request', $events[0]->eventtype);
    }

    public function test_student_cannot_push_resync_request(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session();

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event($session->id, $student->id, 'resync_request', []);
    }

    public function test_teacher_cannot_push_page_or_scroll(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session();

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event($session->id, $teacher->id, 'page', ['url' => '/a']);
    }

    public function test_unrelated_user_cannot_push_any_event(): void {
        $this->resetAfterTest();
        [$session] = $this->setup_active_session();
        $stranger = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event($session->id, $stranger->id, 'page', ['url' => '/a']);
    }

    public function test_push_event_rejected_when_session_not_active(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        // Accepted, but nobody has entered yet: status is 'accepted', not 'active'.

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event($session->id, $student->id, 'page', ['url' => '/a']);
    }

    public function test_teacher_pulls_only_student_sourced_events(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        $transport = new polling_transport();
        $transport->push_event($session->id, $student->id, 'scroll', ['x' => 1, 'y' => 1]);
        $transport->push_event($session->id, $teacher->id, 'resync_request', []);

        $events = $transport->pull_events($session->id, $teacher->id, 0, 20);

        $this->assertCount(1, $events);
        $this->assertSame('scroll', $events[0]->eventtype);
    }

    public function test_student_pulls_only_teacher_sourced_events(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        $transport = new polling_transport();
        $transport->push_event($session->id, $student->id, 'scroll', ['x' => 1, 'y' => 1]);
        $transport->push_event($session->id, $teacher->id, 'resync_request', []);

        $events = $transport->pull_events($session->id, $student->id, 0, 20);

        $this->assertCount(1, $events);
        $this->assertSame('resync_request', $events[0]->eventtype);
    }

    public function test_unrelated_user_cannot_pull_events(): void {
        $this->resetAfterTest();
        [$session] = $this->setup_active_session();
        $stranger = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->pull_events($session->id, $stranger->id, 0, 20);
    }

    public function test_both_roles_can_push_chat_message(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        $transport = new polling_transport();

        $fromstudent = $transport->push_event($session->id, $student->id, 'chat_message', ['message' => 'hola']);
        $fromteacher = $transport->push_event($session->id, $teacher->id, 'chat_message', ['message' => 'hola tambien']);

        $this->assertSame('chat_message', $fromstudent->eventtype);
        $this->assertSame('chat_message', $fromteacher->eventtype);
    }

    public function test_chat_message_pull_includes_own_and_other_messages(): void {
        // Unlike page/scroll/resync_request, chat is bidirectional: each
        // side must see the whole conversation, including what they sent
        // themselves — this is what lets a fresh page load (sinceid reset
        // to 0) replay the full history without a separate endpoint.
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        $transport = new polling_transport();

        $transport->push_event($session->id, $student->id, 'chat_message', ['message' => 'from student']);
        $transport->push_event($session->id, $teacher->id, 'chat_message', ['message' => 'from teacher']);

        $studentview = $transport->pull_events($session->id, $student->id, 0, 20);
        $teacherview = $transport->pull_events($session->id, $teacher->id, 0, 20);

        $this->assertCount(2, $studentview);
        $this->assertCount(2, $teacherview);
    }

    public function test_closing_session_purges_its_events(): void {
        global $DB;
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        (new polling_transport())->push_event($session->id, $student->id, 'scroll', ['x' => 1, 'y' => 1]);

        session_manager::close_session($session->id, $teacher->id);

        $this->assertSame(0, $DB->count_records('local_remotesupport_event', ['sessionid' => $session->id]));
    }

    public function test_page_and_scroll_are_permanently_recorded(): void {
        global $DB;
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session();
        $transport = new polling_transport();

        $transport->push_event($session->id, $student->id, 'page', ['url' => '/a', 'title' => 't', 'html' => '<p>a</p>']);
        $transport->push_event($session->id, $student->id, 'scroll', ['x' => 1, 'y' => 1]);

        $recorded = $DB->get_records('local_remotesupport_track', ['sessionid' => $session->id], 'id ASC');
        $this->assertCount(2, $recorded);
        $types = array_map(static fn ($row) => $row->eventtype, array_values($recorded));
        $this->assertSame(['page', 'scroll'], $types);
    }

    public function test_resync_request_and_chat_message_are_not_recorded(): void {
        global $DB;
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        $transport = new polling_transport();

        $transport->push_event($session->id, $teacher->id, 'resync_request', []);
        $transport->push_event($session->id, $student->id, 'chat_message', ['message' => 'hola']);

        $this->assertCount(0, $DB->get_records('local_remotesupport_track', ['sessionid' => $session->id]));
    }

    public function test_closing_session_does_not_purge_the_recording(): void {
        // The whole point of the recording is to survive the session
        // closing — only an erasure request or the retention-window task
        // removes it (see track_manager_test.php and privacy_provider_test.php).
        global $DB;
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        (new polling_transport())->push_event($session->id, $student->id, 'page', ['url' => '/a']);

        session_manager::close_session($session->id, $teacher->id);

        $this->assertCount(1, $DB->get_records('local_remotesupport_track', ['sessionid' => $session->id]));
    }
}
