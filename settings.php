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
 * Settings for local_remotesupport.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_remotesupport', get_string('pluginname', 'local_remotesupport'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configduration(
        'local_remotesupport/requestexpiryseconds',
        get_string('setting_requestexpiryseconds', 'local_remotesupport'),
        get_string('setting_requestexpiryseconds_desc', 'local_remotesupport'),
        15 * MINSECS,
        MINSECS
    ));

    $settings->add(new admin_setting_configselect(
        'local_remotesupport/capturemode',
        get_string('setting_capturemode', 'local_remotesupport'),
        get_string('setting_capturemode_desc', 'local_remotesupport'),
        'main',
        [
            'main' => get_string('capturemode_main', 'local_remotesupport'),
            'fullpage' => get_string('capturemode_fullpage', 'local_remotesupport'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'local_remotesupport/cursorsamplems',
        get_string('setting_cursorsamplems', 'local_remotesupport'),
        get_string('setting_cursorsamplems_desc', 'local_remotesupport'),
        500,
        [
            200 => get_string('cursorsamplems_200', 'local_remotesupport'),
            500 => get_string('cursorsamplems_500', 'local_remotesupport'),
            1000 => get_string('cursorsamplems_1000', 'local_remotesupport'),
            2000 => get_string('cursorsamplems_2000', 'local_remotesupport'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_remotesupport/clicksound',
        get_string('setting_clicksound', 'local_remotesupport'),
        get_string('setting_clicksound_desc', 'local_remotesupport'),
        1
    ));

    $settings->add(new admin_setting_configselect(
        'local_remotesupport/trackretentiondays',
        get_string('setting_trackretentiondays', 'local_remotesupport'),
        get_string('setting_trackretentiondays_desc', 'local_remotesupport'),
        90,
        [
            15 => get_string('trackretention_15days', 'local_remotesupport'),
            30 => get_string('trackretention_1month', 'local_remotesupport'),
            90 => get_string('trackretention_3months', 'local_remotesupport'),
            180 => get_string('trackretention_6months', 'local_remotesupport'),
            365 => get_string('trackretention_12months', 'local_remotesupport'),
        ]
    ));
}
