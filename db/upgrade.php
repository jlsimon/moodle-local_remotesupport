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
 * Upgrade steps for local_remotesupport.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_remotesupport_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026072601) {
        $table = new xmldb_table('local_remotesupport_event');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourceuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('eventtype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('consumed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('sessionfk', XMLDB_KEY_FOREIGN, ['sessionid'], 'local_remotesupport_session', ['id']);
        $table->add_key('sourceuserfk', XMLDB_KEY_FOREIGN, ['sourceuserid'], 'user', ['id']);

        $table->add_index('sessionidx', XMLDB_INDEX_NOTUNIQUE, ['sessionid', 'id']);
        $table->add_index('consumedtimeidx', XMLDB_INDEX_NOTUNIQUE, ['consumed', 'timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072601, 'local', 'remotesupport');
    }

    if ($oldversion < 2026072604) {
        $table = new xmldb_table('local_remotesupport_session');
        $field = new xmldb_field('controllevel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'view', 'tokenhashteacher');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026072604, 'local', 'remotesupport');
    }

    if ($oldversion < 2026072710) {
        $table = new xmldb_table('local_remotesupport_session');
        $field = new xmldb_field('reason', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'controllevel');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026072710, 'local', 'remotesupport');
    }

    if ($oldversion < 2026072800) {
        // The plugin no longer supports the teacher acting on the student's
        // page (remote cursor/highlight, remote click, remote input, or
        // teacher-driven scroll): it is now view-only, so the consent-level
        // column has nothing left to record.
        $table = new xmldb_table('local_remotesupport_session');
        $field = new xmldb_field('controllevel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'view', 'tokenhashteacher');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026072800, 'local', 'remotesupport');
    }

    if ($oldversion < 2026072900) {
        // Permanent recording of page/scroll events, kept for
        // local_remotesupport/trackretentiondays so a support session can be
        // played back later — deliberately separate from
        // local_remotesupport_event, which stays purely ephemeral.
        $table = new xmldb_table('local_remotesupport_track');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('eventtype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('sessionfk', XMLDB_KEY_FOREIGN, ['sessionid'], 'local_remotesupport_session', ['id']);

        $table->add_index('sessionidx', XMLDB_INDEX_NOTUNIQUE, ['sessionid', 'id']);
        $table->add_index('timecreatedidx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072900, 'local', 'remotesupport');
    }

    return true;
}
