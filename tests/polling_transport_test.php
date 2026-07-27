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

use local_remotesupport\local\control_level;
use local_remotesupport\local\session_manager;
use local_remotesupport\realtime\polling_transport;

/**
 * Tests for polling_transport: role-based push authorization (student
 * pushes page/scroll/click_result, teacher pushes
 * cursor/highlight/resync_request/click_request), the control-level gate
 * on top of role, and the "pull events sourced by the other participant"
 * rule — all independent of the AJAX layer.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class polling_transport_test extends \advanced_testcase {

    /**
     * Create a course with an active session between a student and a
     * teacher, at the given control level (view by default).
     *
     * @param string $level
     * @return array [session, student, teacher]
     */
    private function setup_active_session(string $level = control_level::VIEW): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);
        $token = session_manager::issue_entry_token($session->id, $teacher->id);
        $session = session_manager::enter_session($session->id, $teacher->id, $token);

        if ($level !== control_level::VIEW) {
            $session = session_manager::set_control_level($session->id, $student->id, $level);
        }

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

    public function test_student_cannot_push_cursor_or_highlight(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session(control_level::CLICK);

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event($session->id, $student->id, 'cursor', ['x' => 0.5, 'y' => 0.5]);
    }

    public function test_teacher_can_push_cursor_and_highlight_with_pointer_level(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session(control_level::POINTER);

        $transport = new polling_transport();
        $cursor = $transport->push_event($session->id, $teacher->id, 'cursor', ['x' => 0.5, 'y' => 0.5]);
        $highlight = $transport->push_event($session->id, $teacher->id, 'highlight', ['selector' => '#foo']);

        $this->assertSame('cursor', $cursor->eventtype);
        $this->assertSame('highlight', $highlight->eventtype);
    }

    public function test_teacher_cannot_push_cursor_without_pointer_level(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session(control_level::VIEW);

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event($session->id, $teacher->id, 'cursor', ['x' => 0.5, 'y' => 0.5]);
    }

    public function test_teacher_can_push_scroll_request_with_pointer_level(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session(control_level::POINTER);

        $event = (new polling_transport())->push_event($session->id, $teacher->id, 'scroll_request', ['x' => 10, 'y' => 20]);

        $this->assertSame('scroll_request', $event->eventtype);
    }

    public function test_teacher_cannot_push_scroll_request_without_pointer_level(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session(control_level::VIEW);

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event($session->id, $teacher->id, 'scroll_request', ['x' => 10, 'y' => 20]);
    }

    public function test_student_cannot_push_scroll_request(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session(control_level::POINTER);

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event($session->id, $student->id, 'scroll_request', ['x' => 10, 'y' => 20]);
    }

    public function test_student_receives_scroll_request_pushed_by_teacher(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session(control_level::POINTER);
        $transport = new polling_transport();

        $transport->push_event($session->id, $teacher->id, 'scroll_request', ['x' => 10, 'y' => 20]);
        $events = $transport->pull_events($session->id, $student->id, 0, 20);

        $this->assertCount(1, $events);
        $this->assertSame('scroll_request', $events[0]->eventtype);
    }

    public function test_teacher_can_push_click_request_with_click_level(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session(control_level::CLICK);

        $event = (new polling_transport())->push_event($session->id, $teacher->id, 'click_request', ['selector' => '#foo']);

        $this->assertSame('click_request', $event->eventtype);
    }

    public function test_teacher_cannot_push_click_request_with_only_pointer_level(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session(control_level::POINTER);

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event($session->id, $teacher->id, 'click_request', ['selector' => '#foo']);
    }

    public function test_student_can_always_push_click_result(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session(control_level::VIEW);

        $event = (new polling_transport())->push_event(
            $session->id, $student->id, 'click_result', ['selector' => '#foo', 'outcome' => 'blocked']
        );

        $this->assertSame('click_result', $event->eventtype);
    }

    public function test_teacher_can_push_input_request_with_input_level(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session(control_level::INPUT);

        $event = (new polling_transport())->push_event(
            $session->id, $teacher->id, 'input_request', ['selector' => '#foo', 'action' => 'set_value', 'value' => 'hi']
        );

        $this->assertSame('input_request', $event->eventtype);
    }

    public function test_teacher_cannot_push_input_request_with_only_click_level(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session(control_level::CLICK);

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->push_event(
            $session->id, $teacher->id, 'input_request', ['selector' => '#foo', 'action' => 'set_value', 'value' => 'hi']
        );
    }

    public function test_student_can_always_push_input_result(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session(control_level::VIEW);

        $event = (new polling_transport())->push_event(
            $session->id, $student->id, 'input_result', ['selector' => '#foo', 'action' => 'set_value', 'outcome' => 'blocked']
        );

        $this->assertSame('input_result', $event->eventtype);
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
        [$session, $student, $teacher] = $this->setup_active_session(control_level::POINTER);
        $transport = new polling_transport();
        $transport->push_event($session->id, $student->id, 'scroll', ['x' => 1, 'y' => 1]);
        $transport->push_event($session->id, $teacher->id, 'cursor', ['x' => 0.5, 'y' => 0.5]);

        $events = $transport->pull_events($session->id, $teacher->id, 0, 20);

        $this->assertCount(1, $events);
        $this->assertSame('scroll', $events[0]->eventtype);
    }

    public function test_student_pulls_only_teacher_sourced_events(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session(control_level::POINTER);
        $transport = new polling_transport();
        $transport->push_event($session->id, $student->id, 'scroll', ['x' => 1, 'y' => 1]);
        $transport->push_event($session->id, $teacher->id, 'cursor', ['x' => 0.5, 'y' => 0.5]);

        $events = $transport->pull_events($session->id, $student->id, 0, 20);

        $this->assertCount(1, $events);
        $this->assertSame('cursor', $events[0]->eventtype);
    }

    public function test_unrelated_user_cannot_pull_events(): void {
        $this->resetAfterTest();
        [$session] = $this->setup_active_session();
        $stranger = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        (new polling_transport())->pull_events($session->id, $stranger->id, 0, 20);
    }

    public function test_click_result_triggers_remote_click_audit_event(): void {
        // Regression test: remote_click() was defined in audit_manager but
        // never actually called from anywhere, so no click outcome was ever
        // logged. push_event() must invoke it whenever a click_result is
        // recorded.
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session(control_level::CLICK);
        $sink = $this->redirectEvents();

        (new polling_transport())->push_event(
            $session->id, $student->id, 'click_result', ['selector' => '#foo', 'outcome' => 'clicked']
        );

        $events = array_filter($sink->get_events(), function ($event) {
            return $event instanceof \local_remotesupport\event\remote_click;
        });
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame('clicked', $event->other['outcome']);
    }

    public function test_input_result_triggers_remote_input_audit_event(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session(control_level::INPUT);
        $sink = $this->redirectEvents();

        (new polling_transport())->push_event(
            $session->id, $student->id, 'input_result', ['selector' => '#foo', 'action' => 'set_value', 'outcome' => 'applied']
        );

        $events = array_filter($sink->get_events(), function ($event) {
            return $event instanceof \local_remotesupport\event\remote_input;
        });
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame('applied', $event->other['outcome']);
    }

    public function test_closing_session_purges_its_events(): void {
        global $DB;
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        (new polling_transport())->push_event($session->id, $student->id, 'scroll', ['x' => 1, 'y' => 1]);

        session_manager::close_session($session->id, $teacher->id);

        $this->assertSame(0, $DB->count_records('local_remotesupport_event', ['sessionid' => $session->id]));
    }
}
