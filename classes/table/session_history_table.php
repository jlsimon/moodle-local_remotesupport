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

use local_remotesupport\local\permission_manager;
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

    /** @var string User preference name for the "rows per page" choice on sessionhistory.php. */
    const PREF_PERPAGE = 'local_remotesupport_sessionhistory-perpage';

    /** @var int The teacher viewing this table — every row already belongs to them. */
    private int $teacherid;

    /**
     * @param int $teacherid
     */
    public function __construct(int $teacherid) {
        parent::__construct('local_remotesupport_session_history_' . $teacherid);

        $this->teacherid = $teacherid;

        $this->define_columns([
            'id', 'coursefullname', 'studentfirstname', 'studentlastname', 'timestarted', 'duration',
        ]);
        $this->define_headers([
            get_string('col_sessionnumber', 'local_remotesupport'),
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
     * The session id, as a link to its replay if the viewer is still
     * allowed to replay it (see permission_manager::can_replay_session()),
     * otherwise plain text. Every row already belongs to $this->teacherid
     * (see session_manager::get_closed_sessions_sql_for_teacher()), so a
     * pseudo-session with just the fields that check needs is enough —
     * no need to refetch the full session row per visible row.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function col_id(\stdClass $row): string {
        $pseudosession = (object) ['teacherid' => $this->teacherid, 'courseid' => $row->courseid];
        if (!permission_manager::can_replay_session($pseudosession, $this->teacherid)) {
            return '#' . $row->id;
        }
        $url = new \moodle_url('/local/remotesupport/sessionreplay.php', ['id' => $row->id]);
        return \html_writer::link($url, '#' . $row->id);
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
     * Short duration with labelled units ("1h 15m 30s", "5m 30s", "45s"),
     * instead of format_time()'s verbose "X hours Y mins Z secs" — more
     * concise for a data table where every row shows one, while still
     * unambiguous about which number is which (a bare "1:15:30" reads fine
     * once you know it's H:M:S, but nothing marks it as that on first
     * glance). Moodle core has no standard short duration format to reuse
     * here — unlike dates, only the verbose format_time() exists.
     *
     * @param int $seconds
     * @return string
     */
    private static function format_duration_short(int $seconds): string {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = get_string('durationshort_hours', 'local_remotesupport', $hours);
        }
        if ($hours > 0 || $minutes > 0) {
            $parts[] = get_string('durationshort_minutes', 'local_remotesupport', $minutes);
        }
        $parts[] = get_string('durationshort_seconds', 'local_remotesupport', $secs);

        return implode(' ', $parts);
    }
}
