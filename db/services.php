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
    'local_remotesupport_get_student_status' => [
        'classname' => 'local_remotesupport\external\get_student_status',
        'methodname' => 'execute',
        'description' => 'Poll the student\'s current request/session status, for request.php without a page reload.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_remotesupport_request_assistance' => [
        'classname' => 'local_remotesupport\external\request_assistance',
        'methodname' => 'execute',
        'description' => 'Create an assistance request, for request.php without a page reload.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_remotesupport_cancel_request' => [
        'classname' => 'local_remotesupport\external\cancel_request',
        'methodname' => 'execute',
        'description' => 'Cancel a pending request, for request.php without a page reload.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_remotesupport_enter_session' => [
        'classname' => 'local_remotesupport\external\enter_session',
        'methodname' => 'execute',
        'description' => 'Issue a fresh entry token and return the session.php url, for request.php/view.php without a page reload.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_remotesupport_finish_session' => [
        'classname' => 'local_remotesupport\external\finish_session',
        'methodname' => 'execute',
        'description' => 'End an accepted or active session, for request.php/view.php without a page reload.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_remotesupport_accept_request' => [
        'classname' => 'local_remotesupport\external\accept_request',
        'methodname' => 'execute',
        'description' => 'Accept a pending request and return the session.php url, for view.php without a page reload.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_remotesupport_get_teacher_dashboard' => [
        'classname' => 'local_remotesupport\external\get_teacher_dashboard',
        'methodname' => 'execute',
        'description' => 'Poll the teacher\'s pending/open session lists, for view.php without a page reload.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_remotesupport_get_pending_count' => [
        'classname' => 'local_remotesupport\external\get_pending_count',
        'methodname' => 'execute',
        'description' => 'Poll the teacher\'s pending request count, for the sitewide navbar badge.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
