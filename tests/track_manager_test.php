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

use local_remotesupport\local\track_manager;

/**
 * Tests for track_manager.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class track_manager_test extends \advanced_testcase {
    public function test_record_stores_a_row(): void {
        global $DB;
        $this->resetAfterTest();

        track_manager::record(1, 0, 'page', json_encode(['url' => '/a']));

        $rows = $DB->get_records('local_remotesupport_track', ['sessionid' => 1]);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame('page', $row->eventtype);
        $this->assertSame('/a', json_decode($row->payload, true)['url']);
    }

    public function test_get_track_for_session_returns_events_oldest_first(): void {
        $this->resetAfterTest();

        track_manager::record(111, 5, 'page', json_encode(['url' => '/a']));
        track_manager::record(111, 5, 'scroll', json_encode(['x' => 1, 'y' => 2]));
        track_manager::record(222, 5, 'page', json_encode(['url' => '/other']));

        $track = track_manager::get_track_for_session(111);

        $this->assertCount(2, $track);
        $this->assertSame('page', $track[0]->eventtype);
        $this->assertSame('scroll', $track[1]->eventtype);
        $this->assertSame(5, (int) $track[0]->sourceuserid);
    }

    public function test_get_chat_for_session_returns_only_chat_messages_in_order(): void {
        $this->resetAfterTest();

        track_manager::record(111, 5, 'page', json_encode(['url' => '/a']));
        track_manager::record(111, 5, 'chat_message', json_encode(['message' => 'hola']));
        track_manager::record(111, 7, 'chat_message', json_encode(['message' => 'hola tambien']));
        track_manager::record(111, 5, 'scroll', json_encode(['x' => 1, 'y' => 2]));
        track_manager::record(222, 5, 'chat_message', json_encode(['message' => 'otra sesion']));

        $chat = track_manager::get_chat_for_session(111);

        $this->assertCount(2, $chat);
        $this->assertSame('hola', json_decode($chat[0]->payload, true)['message']);
        $this->assertSame(5, (int) $chat[0]->sourceuserid);
        $this->assertSame('hola tambien', json_decode($chat[1]->payload, true)['message']);
        $this->assertSame(7, (int) $chat[1]->sourceuserid);
    }

    public function test_record_is_isolated_between_sessions(): void {
        global $DB;
        $this->resetAfterTest();

        track_manager::record(111, 0, 'page', json_encode(['url' => '/a']));
        track_manager::record(222, 0, 'page', json_encode(['url' => '/b']));

        $this->assertCount(1, $DB->get_records('local_remotesupport_track', ['sessionid' => 111]));
        $this->assertCount(1, $DB->get_records('local_remotesupport_track', ['sessionid' => 222]));
    }

    public function test_purge_session_track_removes_only_that_session(): void {
        global $DB;
        $this->resetAfterTest();

        track_manager::record(111, 0, 'page', json_encode(['url' => '/a']));
        track_manager::record(222, 0, 'page', json_encode(['url' => '/b']));

        track_manager::purge_session_track(111);

        $this->assertCount(0, $DB->get_records('local_remotesupport_track', ['sessionid' => 111]));
        $this->assertCount(1, $DB->get_records('local_remotesupport_track', ['sessionid' => 222]));
    }

    public function test_purge_stale_track_removes_only_old_rows(): void {
        global $DB;
        $this->resetAfterTest();

        track_manager::record(111, 0, 'page', json_encode(['url' => '/old']));
        $old = $DB->get_records('local_remotesupport_track', ['sessionid' => 111]);
        $oldid = array_key_first($old);
        $DB->set_field('local_remotesupport_track', 'timecreated', time() - (10 * DAYSECS), ['id' => $oldid]);

        track_manager::record(111, 0, 'page', json_encode(['url' => '/fresh']));

        $purged = track_manager::purge_stale_track(5);

        $this->assertSame(1, $purged);
        $remaining = $DB->get_records('local_remotesupport_track', ['sessionid' => 111]);
        $this->assertArrayNotHasKey($oldid, $remaining);
        $this->assertCount(1, $remaining);
    }

    public function test_purge_stale_track_does_nothing_when_retention_not_positive(): void {
        global $DB;
        $this->resetAfterTest();

        track_manager::record(111, 0, 'page', json_encode(['url' => '/a']));
        $rows = $DB->get_records('local_remotesupport_track', ['sessionid' => 111]);
        $DB->set_field('local_remotesupport_track', 'timecreated', 1, ['id' => array_key_first($rows)]);

        $purged = track_manager::purge_stale_track(0);

        $this->assertSame(0, $purged);
        $this->assertCount(1, $DB->get_records('local_remotesupport_track', ['sessionid' => 111]));
    }
}
