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

use local_remotesupport\local\teacher_settings;

/**
 * Tests for teacher_settings.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_settings_test extends \advanced_testcase {
    public function test_support_defaults_to_enabled(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        $this->assertTrue(teacher_settings::is_support_enabled($teacher->id));
    }

    public function test_support_can_be_disabled_and_reenabled(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        teacher_settings::set_support_enabled($teacher->id, false);
        $this->assertFalse(teacher_settings::is_support_enabled($teacher->id));

        teacher_settings::set_support_enabled($teacher->id, true);
        $this->assertTrue(teacher_settings::is_support_enabled($teacher->id));
    }

    public function test_setting_one_teacher_does_not_affect_another(): void {
        $this->resetAfterTest();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();

        teacher_settings::set_support_enabled($teacher1->id, false);

        $this->assertFalse(teacher_settings::is_support_enabled($teacher1->id));
        $this->assertTrue(teacher_settings::is_support_enabled($teacher2->id));
    }

    public function test_support_available_for_course_true_by_default(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->create_and_enrol($course, 'editingteacher');

        $this->assertTrue(teacher_settings::is_support_available_for_course($course->id));
    }

    public function test_support_unavailable_when_only_teacher_disables_it(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        teacher_settings::set_support_enabled($teacher->id, false);

        $this->assertFalse(teacher_settings::is_support_available_for_course($course->id));
    }

    public function test_support_available_when_at_least_one_of_several_teachers_enabled(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher1 = $generator->create_and_enrol($course, 'editingteacher');
        $teacher2 = $generator->create_and_enrol($course, 'editingteacher');

        teacher_settings::set_support_enabled($teacher1->id, false);
        teacher_settings::set_support_enabled($teacher2->id, true);

        $this->assertTrue(teacher_settings::is_support_available_for_course($course->id));
    }

    public function test_support_unavailable_when_no_teacher_in_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse(teacher_settings::is_support_available_for_course($course->id));
    }
}
