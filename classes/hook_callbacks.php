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
 * Hook callback subscribers for local_remotesupport.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Adds the plugin's footer content (session status bar or floating
     * request button) via the modern hook API.
     *
     * On Moodle versions that support this hook, registering it here makes
     * core skip the legacy lib.php::local_remotesupport_before_footer()
     * callback entirely (see get_plugins_with_function()'s
     * migratedtohook handling) instead of emitting a deprecation notice for
     * it — so both must keep doing the same thing, and this one just
     * delegates to it. The legacy function stays in lib.php, unchanged, for
     * Moodle versions predating the hook.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        $hook->add_html(\local_remotesupport_before_footer());
    }
}
