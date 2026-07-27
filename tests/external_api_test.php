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

use local_remotesupport\external\pull_events;
use local_remotesupport\external\push_event;
use local_remotesupport\external\set_control_level;
use local_remotesupport\local\control_level;
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

        $this->assertCount(1, $result);
        $this->assertSame('scroll', $result[0]['eventtype']);
        $decoded = json_decode($result[0]['payload'], true);
        $this->assertSame(1, $decoded['x']);
    }

    public function test_pull_events_rejects_unrelated_user(): void {
        $this->resetAfterTest();
        [$session] = $this->setup_active_session();
        $stranger = $this->getDataGenerator()->create_user();

        $this->setUser($stranger);
        $this->expectException(\moodle_exception::class);
        pull_events::execute($session->id, 0);
    }

    public function test_student_pulls_teacher_cursor_events_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        session_manager::set_control_level($session->id, $student->id, control_level::POINTER);

        $this->setUser($teacher);
        push_event::execute($session->id, 'cursor', json_encode(['x' => 0.4, 'y' => 0.6]));

        $this->setUser($student);
        $result = pull_events::execute($session->id, 0);

        $this->assertCount(1, $result);
        $this->assertSame('cursor', $result[0]['eventtype']);
    }

    public function test_student_cannot_push_cursor_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session();

        $this->setUser($student);
        $this->expectException(\moodle_exception::class);
        push_event::execute($session->id, 'cursor', json_encode(['x' => 0.5, 'y' => 0.5]));
    }

    public function test_teacher_cannot_push_cursor_without_level_end_to_end(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session();
        // Default control level is 'view'; no level was granted.

        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        push_event::execute($session->id, 'cursor', json_encode(['x' => 0.5, 'y' => 0.5]));
    }

    public function test_set_control_level_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student] = $this->setup_active_session();

        $this->setUser($student);
        $result = set_control_level::execute($session->id, control_level::POINTER);

        $this->assertSame(control_level::POINTER, $result['level']);
    }

    public function test_set_control_level_rejects_non_owner_end_to_end(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session();

        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        set_control_level::execute($session->id, control_level::CLICK);
    }

    public function test_click_request_and_result_round_trip_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        session_manager::set_control_level($session->id, $student->id, control_level::CLICK);

        $this->setUser($teacher);
        push_event::execute($session->id, 'click_request', json_encode(['selector' => '#foo']));

        $this->setUser($student);
        $received = pull_events::execute($session->id, 0);
        $this->assertCount(1, $received);
        $this->assertSame('click_request', $received[0]['eventtype']);

        push_event::execute($session->id, 'click_result', json_encode(['selector' => '#foo', 'outcome' => 'blocked']));

        $this->setUser($teacher);
        $result = pull_events::execute($session->id, 0);
        $this->assertCount(1, $result);
        $this->assertSame('click_result', $result[0]['eventtype']);
    }

    public function test_scroll_request_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        session_manager::set_control_level($session->id, $student->id, control_level::POINTER);

        $this->setUser($teacher);
        push_event::execute($session->id, 'scroll_request', json_encode(['x' => 10, 'y' => 20]));

        $this->setUser($student);
        $result = pull_events::execute($session->id, 0);

        $this->assertCount(1, $result);
        $this->assertSame('scroll_request', $result[0]['eventtype']);
        $decoded = json_decode($result[0]['payload'], true);
        $this->assertSame(10, $decoded['x']);
    }

    public function test_teacher_cannot_push_scroll_request_without_pointer_level_end_to_end(): void {
        $this->resetAfterTest();
        [$session, , $teacher] = $this->setup_active_session();
        // Default control level is 'view'; no level was granted.

        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        push_event::execute($session->id, 'scroll_request', json_encode(['x' => 10, 'y' => 20]));
    }

    public function test_input_request_and_result_round_trip_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        session_manager::set_control_level($session->id, $student->id, control_level::INPUT);

        $this->setUser($teacher);
        push_event::execute($session->id, 'input_request', json_encode([
            'selector' => '#foo', 'action' => 'set_value', 'value' => 'hello',
        ]));

        $this->setUser($student);
        $received = pull_events::execute($session->id, 0);
        $this->assertCount(1, $received);
        $this->assertSame('input_request', $received[0]['eventtype']);

        push_event::execute($session->id, 'input_result', json_encode([
            'selector' => '#foo', 'action' => 'set_value', 'outcome' => 'applied',
        ]));

        $this->setUser($teacher);
        $result = pull_events::execute($session->id, 0);
        $this->assertCount(1, $result);
        $this->assertSame('input_result', $result[0]['eventtype']);
    }

    public function test_teacher_cannot_push_input_request_without_input_level_end_to_end(): void {
        $this->resetAfterTest();
        [$session, $student, $teacher] = $this->setup_active_session();
        session_manager::set_control_level($session->id, $student->id, control_level::CLICK);

        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        push_event::execute($session->id, 'input_request', json_encode(['selector' => '#foo', 'action' => 'set_value']));
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
}
