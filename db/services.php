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
 * External functions (AJAX web services) for local_remotesupport.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_remotesupport_push_event' => [
        'classname' => 'local_remotesupport\external\push_event',
        'methodname' => 'execute',
        'description' => 'Store a screen-reconstruction event pushed by the student in an active session.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_remotesupport_pull_events' => [
        'classname' => 'local_remotesupport\external\pull_events',
        'methodname' => 'execute',
        'description' => 'Poll for new screen-reconstruction events as the teacher of an active session.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_remotesupport_set_control_level' => [
        'classname' => 'local_remotesupport\external\set_control_level',
        'methodname' => 'execute',
        'description' => 'Grant or revoke a control level; callable only by the session\'s own student.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
