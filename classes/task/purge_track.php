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

namespace local_remotesupport\task;

use local_remotesupport\local\track_manager;

/**
 * Scheduled task that purges session recordings older than the configured
 * retention window (local_remotesupport/trackretentiondays).
 *
 * Unrelated to purge_events: that task cleans up the ephemeral
 * local_remotesupport_event table on a 2-minute window; this one enforces
 * the much longer, admin-configured retention period for the permanent
 * recording in local_remotesupport_track.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_track extends \core\task\scheduled_task {
    /**
     * Returns the task's localised name, shown in the scheduled tasks admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_purgetrack', 'local_remotesupport');
    }

    /**
     * Purges session recordings older than the configured retention window.
     */
    public function execute(): void {
        $retentiondays = (int) get_config('local_remotesupport', 'trackretentiondays');
        $purged = track_manager::purge_stale_track($retentiondays);
        mtrace("local_remotesupport: purged {$purged} track event(s) older than {$retentiondays} day(s).");
    }
}
