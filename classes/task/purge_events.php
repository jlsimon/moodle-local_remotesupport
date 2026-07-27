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

use local_remotesupport\local\event_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task that purges stale screen-reconstruction events.
 *
 * Complements the immediate purge that session_manager::close_session()
 * already does: this is the safety net for events belonging to sessions
 * that never closed cleanly (crashed client, abandoned tab).
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_events extends \core\task\scheduled_task {

    /** @var int Events older than this, in seconds, are purged regardless of session state. */
    const MAX_EVENT_AGE_SECONDS = 120;

    public function get_name(): string {
        return get_string('task_purgeevents', 'local_remotesupport');
    }

    public function execute(): void {
        $purged = event_manager::purge_stale_events(self::MAX_EVENT_AGE_SECONDS);
        mtrace("local_remotesupport: purged {$purged} stale event(s).");
    }
}
