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

use context_course;

defined('MOODLE_INTERNAL') || die();

/**
 * A teacher's personal preferences for providing remote assistance.
 *
 * Stored as standard Moodle user preferences rather than a dedicated table:
 * this is expected to grow ("more parameters soon"), and user preferences
 * need no schema migration to add another one.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_settings {

    /** @var string Preference name: whether the teacher currently accepts assistance requests. */
    const PREF_SUPPORT_ENABLED = 'local_remotesupport_supportenabled';

    /**
     * Whether a teacher currently accepts assistance requests.
     *
     * Defaults to enabled when the preference has never been set, so
     * upgrading this plugin does not silently stop existing teachers from
     * receiving requests they were already getting.
     *
     * @param int $teacherid
     * @return bool
     */
    public static function is_support_enabled(int $teacherid): bool {
        return (bool) get_user_preferences(self::PREF_SUPPORT_ENABLED, 1, $teacherid);
    }

    /**
     * Set a teacher's own support-enabled preference.
     *
     * @param int $teacherid
     * @param bool $enabled
     */
    public static function set_support_enabled(int $teacherid, bool $enabled): void {
        set_user_preference(self::PREF_SUPPORT_ENABLED, $enabled ? 1 : 0, $teacherid);
    }

    /**
     * Whether at least one teacher able to provide assistance in this course
     * currently has support enabled.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_support_available_for_course(int $courseid): bool {
        $context = context_course::instance($courseid);
        $teachers = get_enrolled_users($context, 'local/remotesupport:provideassistance', 0, 'u.id');

        foreach ($teachers as $teacher) {
            if (self::is_support_enabled((int) $teacher->id)) {
                return true;
            }
        }

        return false;
    }
}
