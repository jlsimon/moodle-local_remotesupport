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

/**
 * Installation smoke tests for local_remotesupport.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Verifies install-time wiring (plugin registration, capability records,
 * scheduled task registration), not the behavior of any single class.
 *
 * @coversNothing
 */
final class plugin_test extends \advanced_testcase {
    public function test_plugin_is_installed(): void {
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('local_remotesupport');
        $this->assertNotNull($plugininfo);
        $this->assertSame('local_remotesupport', $plugininfo->component);
    }

    public function test_capabilities_are_registered(): void {
        global $DB;
        foreach (
            [
            'local/remotesupport:requestassistance',
            'local/remotesupport:provideassistance',
            'local/remotesupport:viewactivesessions',
            'local/remotesupport:managesessions',
            ] as $capability
        ) {
            $this->assertTrue(
                $DB->record_exists('capabilities', ['name' => $capability]),
                "Missing capability: {$capability}"
            );
        }
    }

    public function test_scheduled_task_is_registered(): void {
        $task = \core\task\manager::get_scheduled_task(\local_remotesupport\task\expire_sessions::class);
        $this->assertNotNull($task);
    }
}
