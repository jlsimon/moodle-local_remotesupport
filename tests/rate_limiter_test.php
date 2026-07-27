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

use local_remotesupport\local\rate_limiter;

/**
 * Tests for rate_limiter.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter_test extends \advanced_testcase {

    public function test_unlimited_event_type_always_allowed(): void {
        $this->resetAfterTest();

        $this->assertTrue(rate_limiter::is_allowed(1, 'page'));
        $this->assertTrue(rate_limiter::is_allowed(1, 'page'));
    }

    public function test_second_cursor_event_within_window_is_rejected(): void {
        $this->resetAfterTest();

        $this->assertTrue(rate_limiter::is_allowed(1, 'cursor'));
        $this->assertFalse(rate_limiter::is_allowed(1, 'cursor'));
    }

    public function test_cursor_event_allowed_again_after_window_elapses(): void {
        $this->resetAfterTest();

        $this->assertTrue(rate_limiter::is_allowed(1, 'cursor'));
        usleep((int) (rate_limiter::MIN_INTERVAL_SECONDS['cursor'] * 1_000_000) + 10_000);
        $this->assertTrue(rate_limiter::is_allowed(1, 'cursor'));
    }

    public function test_rate_limit_is_independent_per_session(): void {
        $this->resetAfterTest();

        $this->assertTrue(rate_limiter::is_allowed(1, 'cursor'));
        $this->assertTrue(rate_limiter::is_allowed(2, 'cursor'));
    }

    public function test_second_scroll_event_within_window_is_rejected(): void {
        $this->resetAfterTest();

        $this->assertTrue(rate_limiter::is_allowed(1, 'scroll'));
        $this->assertFalse(rate_limiter::is_allowed(1, 'scroll'));
    }

    public function test_cursor_and_scroll_limits_are_independent_of_each_other(): void {
        $this->resetAfterTest();

        $this->assertTrue(rate_limiter::is_allowed(1, 'cursor'));
        $this->assertTrue(rate_limiter::is_allowed(1, 'scroll'));
    }
}
