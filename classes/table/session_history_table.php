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
     * @var array<int,\stdClass> Session ids on the *current page* known to have at
     *      least one recorded chat_message, keyed by session id — populated by
     *      query_db() below, read by col_chatlink().
     */
    private array $sessionidswithchat = [];

    /**
     * Builds the table's columns/headers and its SQL for this teacher's closed sessions.
     *
     * @param int $teacherid
     */
    public function __construct(int $teacherid) {
        parent::__construct('local_remotesupport_session_history_' . $teacherid);

        $this->teacherid = $teacherid;

        $this->define_columns([
            'select', 'id', 'chatlink', 'coursefullname', 'studentfirstname', 'studentlastname', 'timestarted', 'duration',
        ]);
        $this->define_headers([
            '',
            get_string('col_sessionnumber', 'local_remotesupport'),
            get_string('col_chat', 'local_remotesupport'),
            get_string('col_course', 'local_remotesupport'),
            get_string('col_studentfirstname', 'local_remotesupport'),
            get_string('col_studentlastname', 'local_remotesupport'),
            get_string('col_date', 'local_remotesupport'),
            get_string('col_duration', 'local_remotesupport'),
        ]);

        $sql = session_manager::get_closed_sessions_sql_for_teacher($teacherid);
        $this->set_sql($sql['fields'], $sql['from'], $sql['where'], $sql['params']);

        $this->sortable(true, 'timestarted', SORT_DESC);
        // Columns 'chatlink' and 'select' are not real SQL columns, only a
        // rendered link/checkbox — sorting by either would send an ORDER BY
        // the underlying query can't satisfy.
        $this->no_sorting('chatlink');
        $this->no_sorting('select');
        $this->collapsible(false);
        $this->set_attribute('id', 'local-remotesupport-sessionhistory');
    }

    /**
     * Extends the standard paginated fetch with a single follow-up query —
     * not a subquery per row — to find out, of just the rows on this page,
     * which ones have any recorded chat to show. Lets col_chatlink() grey
     * out the link instead of it always leading somewhere, sometimes to an
     * empty transcript; deliberately not attempted at first (see
     * docs/decisions.md) to avoid a per-row cost, but a single bounded
     * IN (...) query against the current page's ids is cheap enough.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        parent::query_db($pagesize, $useinitialsbar);

        if (!$this->rawdata) {
            return;
        }

        $ids = array_map(static fn ($row) => (int) $row->id, $this->rawdata);
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_QM);
        $params = array_merge(['chat_message'], $inparams);
        $this->sessionidswithchat = $DB->get_records_sql(
            "SELECT DISTINCT sessionid
               FROM {local_remotesupport_track}
              WHERE eventtype = ? AND sessionid $insql",
            $params
        );
    }

    /**
     * A checkbox to include this row in a bulk delete (sessiondelete.php),
     * only shown when the viewer is actually allowed to delete this
     * particular session — same authorization as col_id()/col_chatlink()
     * above, so a row never offers a checkbox that would just be rejected
     * server-side anyway.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function col_select(\stdClass $row): string {
        $pseudosession = (object) ['teacherid' => $this->teacherid, 'courseid' => $row->courseid];
        if (!permission_manager::can_delete_session_history($pseudosession, $this->teacherid)) {
            return '';
        }
        return \html_writer::checkbox('ids[]', $row->id, false, '', ['aria-label' => '#' . $row->id]);
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
        return \html_writer::link($url, '#' . $row->id, [
            'aria-label' => get_string('link_replaysession', 'local_remotesupport', $row->id),
        ]);
    }

    /**
     * A link to sessionchat.php (the full chat transcript alone, without
     * the screen replay), under the same authorization as col_id() — it is
     * the same recorded content, just filtered to one event type. Rendered
     * as a disabled button, not a link, for a session with no chat at all
     * (see query_db() above) — nothing to show there, so nothing to click.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function col_chatlink(\stdClass $row): string {
        $pseudosession = (object) ['teacherid' => $this->teacherid, 'courseid' => $row->courseid];
        if (!permission_manager::can_replay_session($pseudosession, $this->teacherid)) {
            return '-';
        }
        $label = get_string('link_viewchat', 'local_remotesupport');
        if (!isset($this->sessionidswithchat[$row->id])) {
            return \html_writer::tag('button', $label, [
                'type' => 'button',
                'class' => 'btn btn-link btn-sm p-0',
                'disabled' => 'disabled',
            ]);
        }
        $url = new \moodle_url('/local/remotesupport/sessionchat.php', ['id' => $row->id]);
        return \html_writer::link($url, $label);
    }

    /**
     * Renders the course name column, escaped for output.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function col_coursefullname(\stdClass $row): string {
        return format_string($row->coursefullname);
    }

    /**
     * Renders the student's first name column, escaped for output.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function col_studentfirstname(\stdClass $row): string {
        return s($row->studentfirstname);
    }

    /**
     * Renders the student's last name column, escaped for output.
     *
     * @param \stdClass $row
     * @return string
     */
    protected function col_studentlastname(\stdClass $row): string {
        return s($row->studentlastname);
    }

    /**
     * Renders the session's start date/time column, in short format.
     *
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
     * Renders the session's duration column, in short "1h 15m 30s" form.
     *
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
