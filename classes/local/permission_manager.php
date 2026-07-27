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

namespace local_remotesupport\local;

use context;
use context_system;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Centralises capability and ownership checks for the plugin.
 *
 * No other class should call has_capability()/require_capability() directly
 * against the plugin's own capabilities; keeping the rules here means the
 * security model lives in one place.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_manager {

    /**
     * Require that a user can request assistance in a course.
     *
     * @param context $context Course context.
     * @param int|null $userid Defaults to the current user.
     */
    public static function require_can_request(context $context, ?int $userid = null): void {
        require_capability('local/remotesupport:requestassistance', $context, $userid);
    }

    /**
     * Require that a user can provide assistance in a course.
     *
     * @param context $context Course context.
     * @param int|null $userid Defaults to the current user.
     */
    public static function require_can_provide(context $context, ?int $userid = null): void {
        require_capability('local/remotesupport:provideassistance', $context, $userid);
    }

    /**
     * Require that a user can view active/pending sessions in a course.
     *
     * @param context $context Course context.
     * @param int|null $userid Defaults to the current user.
     */
    public static function require_can_view_active(context $context, ?int $userid = null): void {
        require_capability('local/remotesupport:viewactivesessions', $context, $userid);
    }

    /**
     * Whether the given user can manage any session (administrative override).
     *
     * @param int|null $userid Defaults to the current user.
     * @return bool
     */
    public static function can_manage(?int $userid = null): bool {
        return has_capability('local/remotesupport:managesessions', context_system::instance(), $userid);
    }

    /**
     * Require that a user can view the teacher dashboard (view.php): either
     * they hold viewactivesessions in at least one course, or they can
     * manage sessions site-wide.
     *
     * @param int $userid
     * @throws moodle_exception
     */
    public static function require_can_view_dashboard(int $userid): void {
        if (!get_user_capability_course('local/remotesupport:viewactivesessions', $userid, false) && !self::can_manage($userid)) {
            throw new moodle_exception('errornopermission', 'local_remotesupport');
        }
    }

    /**
     * Require that a user can provide assistance in at least one course
     * (regardless of which one) — used by the navbar badge poll, which is
     * not scoped to a single course context.
     *
     * @param int $userid
     * @throws moodle_exception
     */
    public static function require_can_provide_anywhere(int $userid): void {
        if (!get_user_capability_course('local/remotesupport:provideassistance', $userid, false)) {
            throw new moodle_exception('errornopermission', 'local_remotesupport');
        }
    }

    /**
     * Require that the current user owns the session (as student or teacher)
     * or holds the managesessions capability.
     *
     * Deliberately does not distinguish "session does not exist" from
     * "session belongs to someone else" in the exception shown to the user,
     * to avoid confirming session ids belong to real records.
     *
     * @param \stdClass $session Row from local_remotesupport_session.
     * @param int $userid
     * @throws moodle_exception
     */
    public static function require_owner_or_manage(\stdClass $session, int $userid): void {
        if ($session->studentid == $userid || $session->teacherid == $userid) {
            return;
        }
        if (self::can_manage($userid)) {
            return;
        }
        throw new moodle_exception('errornopermission', 'local_remotesupport');
    }
}
