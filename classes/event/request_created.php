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

namespace local_remotesupport\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when a student creates a remote assistance request.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_created extends \core\event\base {

    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_remotesupport_session';
    }

    public static function get_name(): string {
        return get_string('event_request_created', 'local_remotesupport');
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' requested remote assistance " .
            "(session id '{$this->objectid}') in the course with id '{$this->courseid}'.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/remotesupport/request.php', ['id' => $this->courseid]);
    }
}
