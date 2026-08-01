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
use local_remotesupport\local\teacher_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/remotesupport/lib.php');

/**
 * Tests for lib.php callbacks.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_remotesupport_before_footer
 * @covers     ::local_remotesupport_render_floating_request_button
 * @covers     ::local_remotesupport_render_navbar_output
 */
final class lib_test extends \advanced_testcase {
    public function test_navbar_output_empty_for_guest(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        global $PAGE;
        $renderer = $PAGE->get_renderer('core');

        $this->assertSame('', local_remotesupport_render_navbar_output($renderer));
    }

    public function test_navbar_output_empty_for_logged_out_user(): void {
        $this->resetAfterTest();

        global $PAGE;
        $renderer = $PAGE->get_renderer('core');

        $this->assertSame('', local_remotesupport_render_navbar_output($renderer));
    }

    public function test_navbar_output_shown_without_badge_when_no_pending_requests(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');
        $output = local_remotesupport_render_navbar_output($renderer);

        // The icon is now always shown to anyone who could provide
        // assistance somewhere, since it is also how they reach their
        // personal settings — but with nothing pending, no numeric badge.
        $this->assertStringContainsString('view.php', $output);
        $this->assertStringNotContainsString('local-remotesupport-navbar-badge', $output);
        // Support is enabled by default, so no "unavailable" slash either.
        $this->assertStringNotContainsString('local-remotesupport-navbar-unavailable', $output);
    }

    public function test_navbar_output_marks_icon_unavailable_when_teacher_disables_support(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        teacher_settings::set_support_enabled($teacher->id, false);

        $this->setUser($teacher);
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');
        $output = local_remotesupport_render_navbar_output($renderer);

        $this->assertStringContainsString('local-remotesupport-navbar-unavailable', $output);
        $this->assertStringContainsString('Remote assistance (you are currently unavailable)', $output);
    }

    public function test_navbar_output_no_unavailable_marker_when_teacher_enables_support(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        teacher_settings::set_support_enabled($teacher->id, true);

        $this->setUser($teacher);
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');
        $output = local_remotesupport_render_navbar_output($renderer);

        $this->assertStringNotContainsString('local-remotesupport-navbar-unavailable', $output);
    }

    public function test_navbar_output_shows_count_and_link_when_pending(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        session_manager::create_request($course->id, $student->id);

        $this->setUser($teacher);
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');
        $output = local_remotesupport_render_navbar_output($renderer);

        $this->assertStringContainsString('>1<', $output);
        $this->assertStringContainsString('view.php', $output);
    }

    public function test_navbar_output_count_matches_multiple_pending_requests(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        session_manager::create_request($course->id, $student1->id);
        session_manager::create_request($course->id, $student2->id);

        $this->setUser($teacher);
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');
        $output = local_remotesupport_render_navbar_output($renderer);

        $this->assertStringContainsString('>2<', $output);
    }

    public function test_navbar_output_empty_for_teacher_without_capability_in_any_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        session_manager::create_request($course->id, $student->id);

        // A user with no role anywhere in this course cannot provide
        // assistance in it, so the pending request above must not surface.
        $stranger = $generator->create_user();
        $this->setUser($stranger);
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');

        $this->assertSame('', local_remotesupport_render_navbar_output($renderer));
    }

    public function test_floating_button_empty_for_student_without_qualifying_course(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();

        $this->assertSame('', local_remotesupport_render_floating_request_button($student->id));
    }

    public function test_floating_button_links_directly_to_single_qualifying_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        $output = local_remotesupport_render_floating_request_button($student->id);

        $this->assertStringContainsString('request.php', $output);
        $this->assertStringContainsString('id=' . $course->id, $output);
        $this->assertStringNotContainsString('<details', $output);
    }

    public function test_floating_button_shows_picker_for_multiple_qualifying_courses(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course(['fullname' => 'Course Alpha']);
        $course2 = $generator->create_course(['fullname' => 'Course Beta']);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course1->id, 'student');
        $generator->enrol_user($student->id, $course2->id, 'student');

        $output = local_remotesupport_render_floating_request_button($student->id);

        $this->assertStringContainsString('<details', $output);
        $this->assertStringContainsString('Course Alpha', $output);
        $this->assertStringContainsString('Course Beta', $output);
    }

    public function test_floating_button_shows_view_link_when_open_request_exists(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course1->id, 'student');
        $generator->enrol_user($student->id, $course2->id, 'student');
        session_manager::create_request($course1->id, $student->id);

        $output = local_remotesupport_render_floating_request_button($student->id);

        $this->assertStringContainsString('id=' . $course1->id, $output);
        $this->assertStringNotContainsString('<details', $output);
    }

    public function test_before_footer_returns_floating_button_on_ordinary_page(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $output = local_remotesupport_before_footer();

        $this->assertStringContainsString('request.php', $output);
    }

    public function test_before_footer_empty_for_guest(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->assertSame('', local_remotesupport_before_footer());
    }
}
