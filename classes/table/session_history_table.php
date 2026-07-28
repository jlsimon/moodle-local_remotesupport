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

namespace local_remotesupport\table;

use local_remotesupport\local\session_manager;
use table_sql;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Sortable, paginated list of a teacher's closed support sessions.
 *
 * Standard Moodle \table_sql: sorting/pagination happen at the SQL level via
 * GET params on the page's own URL, no AJAX or JavaScript involved. The SQL
 * fragments come from session_manager, not this class, so session_manager
 * stays the only place that knows the session table's shape.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_history_table extends table_sql {

    /**
     * @param int $teacherid
     */
    public function __construct(int $teacherid) {
        parent::__construct('local_remotesupport_session_history_' . $teacherid);

        $this->define_columns([
            'coursefullname', 'studentfirstname', 'studentlastname', 'timestarted', 'duration',
        ]);
        $this->define_headers([
            get_string('col_course', 'local_remotesupport'),
            get_string('col_studentfirstname', 'local_remotesupport'),
            get_string('col_studentlastname', 'local_remotesupport'),
            get_string('col_date', 'local_remotesupport'),
            get_string('col_duration', 'local_remotesupport'),
        ]);

        $sql = session_manager::get_closed_sessions_sql_for_teacher($teacherid);
        $this->set_sql($sql['fields'], $sql['from'], $sql['where'], $sql['params']);

        $this->sortable(true, 'timestarted', SORT_DESC);
        $this->collapsible(false);
        $this->set_attribute('id', 'local-remotesupport-sessionhistory');
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    protected function col_coursefullname(\stdClass $row): string {
        return format_string($row->coursefullname);
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    protected function col_studentfirstname(\stdClass $row): string {
        return s($row->studentfirstname);
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    protected function col_studentlastname(\stdClass $row): string {
        return s($row->studentlastname);
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    protected function col_timestarted(\stdClass $row): string {
        if (!$row->timestarted) {
            return '-';
        }
        // Short format, consistent with the "waiting since" column on the
        // pending-requests table in view.php — a data table like this one
        // favours conciseness over the verbose default userdate() format.
        return userdate($row->timestarted, get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * @param \stdClass $row
     * @return string
     */
    protected function col_duration(\stdClass $row): string {
        if (!$row->timestarted || !$row->timeended) {
            return '-';
        }
        return self::format_duration_short($row->timeended - $row->timestarted);
    }

    /**
     * Stopwatch-style short duration (M:SS, or H:MM:SS once past an hour),
     * instead of format_time()'s verbose "X mins Y secs" — more concise for
     * a data table where every row shows one.
     *
     * @param int $seconds
     * @return string
     */
    private static function format_duration_short(int $seconds): string {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%d:%02d', $minutes, $secs);
    }
}
