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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_remotesupport\local\session_manager;
use local_remotesupport\local\track_manager;
use local_remotesupport\privacy\provider;

/**
 * Privacy provider tests for local_remotesupport.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        session_manager::create_request($course->id, $student->id);

        $coursecontext = \context_course::instance($course->id);

        $contextlist = provider::get_contexts_for_userid($student->id);
        $this->assertContains((int) $coursecontext->id, array_map('intval', $contextlist->get_contextids()));

        $contextlist = provider::get_contexts_for_userid($teacher->id);
        $this->assertEmpty($contextlist->get_contextids());
    }

    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        session_manager::create_request($course->id, $student->id, 'No puedo enviar la tarea');
        $coursecontext = \context_course::instance($course->id);

        $approved = new approved_contextlist($student, 'local_remotesupport', [$coursecontext->id]);
        provider::export_user_data($approved);

        $exported = writer::with_context($coursecontext)->get_data([get_string('privacy:path', 'local_remotesupport')]);
        $this->assertNotEmpty($exported->sessions);
        $this->assertSame('student', $exported->sessions[0]->role);
        $this->assertSame('No puedo enviar la tarea', $exported->sessions[0]->reason);
    }

    public function test_delete_data_for_user(): void {
        $this->resetAfterTest();
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        $session = session_manager::create_request($course->id, $student->id);
        $coursecontext = \context_course::instance($course->id);

        $approved = new approved_contextlist($student, 'local_remotesupport', [$coursecontext->id]);
        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('local_remotesupport_session', ['id' => $session->id]));
    }

    public function test_delete_data_for_user_also_purges_track(): void {
        // Regression: the session recording is deliberately deleted on an
        // erasure request even though it survives a normal session close —
        // see docs/decisions.md for why (the recorded content is
        // fundamentally the student's own activity).
        $this->resetAfterTest();
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        $session = session_manager::create_request($course->id, $student->id);
        track_manager::record($session->id, $student->id, 'page', json_encode(['url' => '/a']));
        $coursecontext = \context_course::instance($course->id);

        $approved = new approved_contextlist($student, 'local_remotesupport', [$coursecontext->id]);
        provider::delete_data_for_user($approved);

        $this->assertSame(0, $DB->count_records('local_remotesupport_track', ['sessionid' => $session->id]));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        $this->resetAfterTest();
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        $session = session_manager::create_request($course->id, $student->id);
        $coursecontext = \context_course::instance($course->id);

        provider::delete_data_for_all_users_in_context($coursecontext);

        $this->assertFalse($DB->record_exists('local_remotesupport_session', ['id' => $session->id]));
    }

    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $session = session_manager::create_request($course->id, $student->id);
        session_manager::accept_request($session->id, $teacher->id);

        $coursecontext = \context_course::instance($course->id);
        $userlist = new userlist($coursecontext, 'local_remotesupport');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        $this->assertContains((int) $student->id, $userids);
        $this->assertContains((int) $teacher->id, $userids);
    }
}
