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

/**
 * Scheduled tasks for local_remotesupport.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_remotesupport\task\expire_sessions',
        'blocking' => 0,
        'minute' => '*/5',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    [
        'classname' => 'local_remotesupport\task\purge_events',
        'blocking' => 0,
        'minute' => '*/1',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];
