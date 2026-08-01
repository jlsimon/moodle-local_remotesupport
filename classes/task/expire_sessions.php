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

use local_remotesupport\local\session_manager;

/**
 * Scheduled task that expires pending assistance requests past their deadline.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class expire_sessions extends \core\task\scheduled_task {
    /**
     * Returns the task's localised name, shown in the scheduled tasks admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_expiresessions', 'local_remotesupport');
    }

    /**
     * Expires pending assistance requests whose deadline has passed.
     */
    public function execute(): void {
        $expired = session_manager::expire_stale_requests();
        mtrace("local_remotesupport: expired {$expired} pending request(s).");
    }
}
