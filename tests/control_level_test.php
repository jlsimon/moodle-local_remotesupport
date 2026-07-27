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

/**
 * Tests for control_level.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class control_level_test extends \basic_testcase {

    public function test_all_returns_the_four_levels(): void {
        $this->assertSame(['view', 'pointer', 'click', 'input'], control_level::all());
    }

    public function test_at_least_same_level_is_true(): void {
        $this->assertTrue(control_level::at_least(control_level::CLICK, control_level::CLICK));
    }

    public function test_at_least_higher_level_is_true(): void {
        $this->assertTrue(control_level::at_least(control_level::CLICK, control_level::VIEW));
        $this->assertTrue(control_level::at_least(control_level::INPUT, control_level::CLICK));
    }

    public function test_at_least_lower_level_is_false(): void {
        $this->assertFalse(control_level::at_least(control_level::VIEW, control_level::POINTER));
        $this->assertFalse(control_level::at_least(control_level::POINTER, control_level::CLICK));
    }

    public function test_unknown_level_is_never_sufficient(): void {
        $this->assertFalse(control_level::at_least('bogus', control_level::VIEW));
    }
}
