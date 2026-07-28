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

use local_remotesupport\local\permission_manager;

/**
 * Tests for permission_manager.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_manager_test extends \advanced_testcase {

    public function test_require_can_view_history_allows_teacher(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        // No exception means allowed.
        permission_manager::require_can_view_history($teacher->id);
        $this->assertTrue(true);
    }

    public function test_require_can_view_history_rejects_user_without_capability(): void {
        $this->resetAfterTest();
        $stranger = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        permission_manager::require_can_view_history($stranger->id);
    }

    public function test_require_can_view_history_allows_manager_override(): void {
        global $DB;
        $this->resetAfterTest();
        $manager = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($roleid, $manager->id, \context_system::instance()->id);

        permission_manager::require_can_view_history($manager->id);
        $this->assertTrue(true);
    }
}
