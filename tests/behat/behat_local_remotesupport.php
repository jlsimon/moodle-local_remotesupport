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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Page-object step definitions for local_remotesupport.
 *
 * @package    local_remotesupport
 * @category   test
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_remotesupport extends behat_base {

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page types are:
     * | Page type           | Identifier meaning | description                                        |
     * | Request assistance  | course shortname    | Student's request/status page (request.php).     |
     *
     * The teacher dashboard (view.php) has no per-course identifier, so it
     * is reached instead with the core "I visit" step.
     *
     * @param string $type identifies which type of page this is, e.g. 'Request assistance'.
     * @param string $identifier identifies the particular page, e.g. a course shortname.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'request assistance':
                return new moodle_url('/local/remotesupport/request.php', [
                    'id' => $this->get_course_id($identifier),
                ]);

            default:
                throw new Exception('Unrecognised local_remotesupport page type "' . $type . '"');
        }
    }
}
