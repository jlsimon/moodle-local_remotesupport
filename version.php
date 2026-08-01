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
 * Version information for local_remotesupport.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_remotesupport';
$plugin->version = 2026080110;
// Moodle 4.2: the AJAX layer (classes/external/*.php) uses the namespaced
// core_external\external_api classes, which do not exist before 4.2 —
// confirmed by CI, not just a documentation note (see docs/limitations.md).
$plugin->requires = 2023042400;
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.25.1 (Harden site-wide hooks against leftover code after an incomplete uninstall)';
