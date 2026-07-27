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

use local_remotesupport\local\event_manager;

/**
 * Tests for event_manager.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_manager_test extends \advanced_testcase {

    public function test_record_and_pull_events_in_order(): void {
        $this->resetAfterTest();

        $e1 = event_manager::record_event(1, 2, 'page', ['url' => '/a', 'title' => 't', 'html' => '<p>a</p>']);
        $e2 = event_manager::record_event(1, 2, 'scroll', ['x' => 10, 'y' => 20]);

        $events = event_manager::get_events_since(1, 0, 999);

        $this->assertCount(2, $events);
        $this->assertEquals($e1->id, $events[0]->id);
        $this->assertEquals($e2->id, $events[1]->id);
        $this->assertSame('page', $events[0]->eventtype);
        $this->assertSame('/a', $events[0]->payload['url']);
        $this->assertSame(10, $events[1]->payload['x']);
    }

    public function test_pull_events_since_cursor_excludes_earlier_events(): void {
        $this->resetAfterTest();

        // Uses 'highlight', not 'scroll': two of the same rate-limited type
        // fired back-to-back in the same session would have the second one
        // silently dropped, which is not what this ordering test is about.
        $e1 = event_manager::record_event(1, 2, 'highlight', ['selector' => '#a']);
        event_manager::record_event(1, 2, 'highlight', ['selector' => '#b']);

        $events = event_manager::get_events_since(1, $e1->id, 999);

        $this->assertCount(1, $events);
        $this->assertSame('#b', $events[0]->payload['selector']);
    }

    public function test_pull_events_marks_rows_consumed(): void {
        global $DB;
        $this->resetAfterTest();

        $event = event_manager::record_event(1, 2, 'scroll', ['x' => 1, 'y' => 1]);
        event_manager::get_events_since(1, 0, 999);

        $stored = $DB->get_record('local_remotesupport_event', ['id' => $event->id]);
        $this->assertEquals(1, $stored->consumed);
    }

    public function test_rejects_unknown_event_type(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        event_manager::record_event(1, 2, 'eval', ['x' => 1]);
    }

    public function test_rejects_non_numeric_cursor_coordinates(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        event_manager::record_event(1, 2, 'cursor', ['x' => 'not-a-number', 'y' => 0.5]);
    }

    public function test_rejects_scroll_missing_coordinate(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        event_manager::record_event(1, 2, 'scroll', ['x' => 1]);
    }

    public function test_rejects_scroll_request_non_numeric_coordinates(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        event_manager::record_event(1, 2, 'scroll_request', ['x' => 1, 'y' => ['nested' => 'array']]);
    }

    public function test_rejects_highlight_without_selector(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        event_manager::record_event(1, 2, 'highlight', []);
    }

    public function test_rejects_highlight_empty_selector(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        event_manager::record_event(1, 2, 'highlight', ['selector' => '']);
    }

    public function test_rejects_oversized_payload(): void {
        $this->resetAfterTest();

        // Use 'scroll', not 'page': a 'page' event's html is truncated by the
        // sanitizer before the size check runs, so it would never trip this.
        $this->expectException(\moodle_exception::class);
        event_manager::record_event(1, 2, 'scroll', ['x' => 1, 'junk' => str_repeat('a', event_manager::MAX_PAYLOAD_BYTES + 10)]);
    }

    public function test_page_event_html_is_sanitized_on_storage(): void {
        $this->resetAfterTest();

        $event = event_manager::record_event(1, 2, 'page', [
            'url' => '/a',
            'title' => 't',
            'html' => '<p>ok</p><script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script', $event->payload);
        $this->assertStringNotContainsString('alert(1)', $event->payload);
    }

    public function test_events_are_isolated_between_sessions(): void {
        $this->resetAfterTest();

        event_manager::record_event(111, 2, 'scroll', ['x' => 1, 'y' => 1]);
        event_manager::record_event(222, 3, 'scroll', ['x' => 2, 'y' => 2]);

        $this->assertCount(1, event_manager::get_events_since(111, 0, 999));
        $this->assertCount(1, event_manager::get_events_since(222, 0, 999));
    }

    public function test_purge_session_events_removes_only_that_session(): void {
        $this->resetAfterTest();

        event_manager::record_event(111, 2, 'scroll', ['x' => 1, 'y' => 1]);
        event_manager::record_event(222, 3, 'scroll', ['x' => 2, 'y' => 2]);

        event_manager::purge_session_events(111);

        $this->assertCount(0, event_manager::get_events_since(111, 0, 999));
        $this->assertCount(1, event_manager::get_events_since(222, 0, 999));
    }

    public function test_get_events_since_excludes_own_events(): void {
        $this->resetAfterTest();

        event_manager::record_event(1, 2, 'page', ['url' => '/a']);
        event_manager::record_event(1, 3, 'cursor', ['x' => 0.5, 'y' => 0.5]);

        $forteacher = event_manager::get_events_since(1, 0, 3);
        $forstudent = event_manager::get_events_since(1, 0, 2);

        $this->assertCount(1, $forteacher);
        $this->assertSame('page', $forteacher[0]->eventtype);
        $this->assertCount(1, $forstudent);
        $this->assertSame('cursor', $forstudent[0]->eventtype);
    }

    public function test_cursor_and_highlight_event_types_accepted(): void {
        $this->resetAfterTest();

        $cursor = event_manager::record_event(1, 2, 'cursor', ['x' => 0.1, 'y' => 0.2]);
        $highlight = event_manager::record_event(1, 2, 'highlight', ['selector' => '#foo']);

        $this->assertSame('cursor', $cursor->eventtype);
        $this->assertSame('highlight', $highlight->eventtype);
    }

    public function test_cursor_events_are_rate_limited(): void {
        $this->resetAfterTest();

        $first = event_manager::record_event(1, 2, 'cursor', ['x' => 0.1, 'y' => 0.1]);
        $second = event_manager::record_event(1, 2, 'cursor', ['x' => 0.2, 'y' => 0.2]);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertCount(1, event_manager::get_events_since(1, 0, 999));
    }

    public function test_resync_request_event_type_accepted(): void {
        $this->resetAfterTest();

        $event = event_manager::record_event(1, 2, 'resync_request', []);

        $this->assertSame('resync_request', $event->eventtype);
    }

    public function test_input_request_accepts_recognised_actions(): void {
        $this->resetAfterTest();

        foreach (event_manager::INPUT_ACTIONS as $action) {
            $event = event_manager::record_event(1, 2, 'input_request', ['selector' => '#foo', 'action' => $action, 'value' => 'x']);
            $this->assertSame($action, $event->payload['action'] ?? json_decode($event->payload, true)['action']);
        }
    }

    public function test_input_request_rejects_unrecognised_action(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        event_manager::record_event(1, 2, 'input_request', ['selector' => '#foo', 'action' => 'eval']);
    }

    public function test_input_request_rejects_missing_action(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        event_manager::record_event(1, 2, 'input_request', ['selector' => '#foo']);
    }

    public function test_input_request_truncates_overlong_value(): void {
        $this->resetAfterTest();

        $event = event_manager::record_event(1, 2, 'input_request', [
            'selector' => '#foo',
            'action' => 'set_value',
            'value' => str_repeat('a', event_manager::MAX_INPUT_VALUE_LENGTH + 500),
        ]);

        $decoded = json_decode($event->payload, true);
        $this->assertSame(event_manager::MAX_INPUT_VALUE_LENGTH, strlen($decoded['value']));
    }

    public function test_input_result_event_type_accepted(): void {
        $this->resetAfterTest();

        $event = event_manager::record_event(1, 2, 'input_result', ['selector' => '#foo', 'action' => 'clear', 'outcome' => 'applied']);

        $this->assertSame('input_result', $event->eventtype);
    }

    public function test_scroll_events_are_rate_limited(): void {
        $this->resetAfterTest();

        $first = event_manager::record_event(1, 2, 'scroll', ['x' => 1, 'y' => 1]);
        $second = event_manager::record_event(1, 2, 'scroll', ['x' => 2, 'y' => 2]);

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function test_scroll_request_event_type_accepted(): void {
        $this->resetAfterTest();

        $event = event_manager::record_event(1, 2, 'scroll_request', ['x' => 10, 'y' => 20]);

        $this->assertSame('scroll_request', $event->eventtype);
    }

    public function test_scroll_request_events_are_rate_limited(): void {
        $this->resetAfterTest();

        $first = event_manager::record_event(1, 2, 'scroll_request', ['x' => 1, 'y' => 1]);
        $second = event_manager::record_event(1, 2, 'scroll_request', ['x' => 2, 'y' => 2]);

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function test_page_event_keeps_only_same_origin_stylesheets(): void {
        global $CFG;
        $this->resetAfterTest();

        $event = event_manager::record_event(1, 2, 'page', [
            'url' => '/a',
            'css' => [$CFG->wwwroot . '/theme/styles.php/boost/1/all', 'https://evil.example/steal.css'],
        ]);

        $payload = json_decode($event->payload, true);
        $this->assertCount(1, $payload['css']);
        $this->assertSame($CFG->wwwroot . '/theme/styles.php/boost/1/all', $payload['css'][0]);
    }

    public function test_page_event_modal_html_is_sanitized_on_storage(): void {
        $this->resetAfterTest();

        $event = event_manager::record_event(1, 2, 'page', [
            'url' => '/a',
            'modal' => '<div class="modal">ok</div><script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script', $event->payload);
        $this->assertStringNotContainsString('alert(1)', $event->payload);
        $this->assertStringContainsString('modal', $event->payload);
    }

    public function test_purge_stale_events_removes_only_old_rows(): void {
        global $DB;
        $this->resetAfterTest();

        // Uses 'highlight', not 'scroll': this test is about age-based
        // purging, and a second rate-limited 'scroll' fired right after the
        // first would be dropped rather than stored.
        $old = event_manager::record_event(111, 2, 'highlight', ['selector' => '#old']);
        $DB->set_field('local_remotesupport_event', 'timecreated', time() - 999, ['id' => $old->id]);
        $fresh = event_manager::record_event(111, 2, 'highlight', ['selector' => '#fresh']);

        $purged = event_manager::purge_stale_events(120);

        $this->assertSame(1, $purged);
        $remaining = $DB->get_records('local_remotesupport_event', ['sessionid' => 111]);
        $this->assertArrayHasKey($fresh->id, $remaining);
        $this->assertArrayNotHasKey($old->id, $remaining);
    }
}
