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
 * Library callbacks for local_remotesupport.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Whether the plugin's own tables and config are actually present.
 *
 * db/hooks.php's before_footer subscriber and the PLUGIN_render_navbar_output
 * callback below are discovered by Moodle from files on disk
 * (get_plugins_with_function() scans component directories, not
 * config_plugins), independently of whether the plugin is actually installed
 * in the database. So leftover code from an uninstall that removed the
 * tables/config but not the files — which is exactly what a plugin
 * uninstalled through the Moodle UI leaves behind until an admin also
 * deletes the code — would otherwise still have both callbacks fire on
 * every page for every logged-in user. Both check this first and bail out
 * cleanly rather than let a missing table take down the whole site.
 *
 * get_config() reads from the (cached) config_plugins table, so this adds
 * no real per-page cost, and is checked first specifically to avoid even
 * attempting the session table query below in the common case.
 *
 * @return bool
 */
function local_remotesupport_is_installed(): bool {
    return get_config('local_remotesupport', 'version') !== false;
}

/**
 * Local (site-relative) url of the page currently being rendered, for the
 * "return here once the session starts" links built below — see
 * session_manager::create_request()'s $returnurl and session.php.
 *
 * has_set_url() is checked first, not just wrapped in a try/catch: reading
 * $PAGE->url before the page has called $PAGE->set_url() does not throw,
 * it triggers a debugging() notice and falls back to guessing a url from
 * the request — noisy (fails PHPUnit's "no unexpected debugging calls"
 * check) for what is only ever a best-effort convenience link, not
 * something worth surfacing as a warning. $PAGE->url is always set to a
 * local path by Moodle convention throughout core and this plugin once it
 * *is* set, so out_as_local_url() itself should never actually throw, but
 * this hook runs on essentially every page's navigation build; an
 * uncaught exception here would break course navigation site-wide for the
 * rare page that violates that convention, an outsized cost for a link
 * that is itself only a minor convenience — caught defensively regardless.
 *
 * @return string Local url, or '' if it could not be determined safely.
 */
function local_remotesupport_current_page_local_url(): string {
    global $PAGE;
    if (!$PAGE->has_set_url()) {
        return '';
    }
    try {
        return $PAGE->url->out_as_local_url(false);
    } catch (moodle_exception $e) {
        return '';
    }
}

/**
 * Add navigation links into the course menu so the plugin's pages are reachable.
 *
 * @param navigation_node $parentnode
 * @param stdClass $course
 * @param context_course $context
 */
function local_remotesupport_extend_navigation_course(
    navigation_node $parentnode,
    stdClass $course,
    context_course $context
): void {
    if (has_capability('local/remotesupport:requestassistance', $context)) {
        $parentnode->add(
            get_string('pagetitle_request', 'local_remotesupport'),
            new moodle_url('/local/remotesupport/request.php', [
                'id' => $course->id,
                'fromurl' => local_remotesupport_current_page_local_url(),
            ]),
            navigation_node::TYPE_SETTING,
            null,
            'local_remotesupport_request'
        );
    }

    if (has_capability('local/remotesupport:viewactivesessions', $context)) {
        $parentnode->add(
            get_string('pagetitle_view', 'local_remotesupport'),
            new moodle_url('/local/remotesupport/view.php'),
            navigation_node::TYPE_SETTING,
            null,
            'local_remotesupport_view'
        );
    }
}

/**
 * Injects the screen-capture module into every Moodle page while the
 * current user is the student of an active assistance session, or,
 * failing that, renders the site-wide floating "request assistance"
 * button so a student can reach the request page even when the course
 * menu isn't reachable (e.g. from the dashboard, or a broken page).
 *
 * Cheap checks (installed?, login state, page type) run first and
 * short-circuit before the one indexed database lookup this performs per
 * page load. The plugin's own pages are excluded so neither element is
 * ever shown on top of the page that already covers the same information.
 *
 * The session/request lookups are also wrapped in a dml_exception catch,
 * on top of the local_remotesupport_is_installed() check above, as a second
 * line of defense against any other broken-schema state (e.g. an
 * interrupted upgrade) that check would not catch — see its own comment.
 *
 * The legacy before_footer callback's return value is appended to the
 * page footer by core (see before_footer_html_generation::process_legacy_callbacks()),
 * which is why this returns HTML instead of echoing it.
 *
 * Also passes the site's configured capture mode ('main', the default, or
 * 'fullpage') and cursor position sampling rate to event_capture.js — both
 * single admin settings (local_remotesupport/capturemode,
 * local_remotesupport/cursorsamplems) applied to every session on the
 * site, not a per-session/per-teacher choice; see docs/decisions.md.
 *
 * @return string HTML to append to the footer, or '' if nothing to show.
 */
function local_remotesupport_before_footer(): string {
    global $PAGE, $USER;

    if (!local_remotesupport_is_installed()) {
        return '';
    }
    if (!isloggedin() || isguestuser()) {
        return '';
    }
    if (strpos((string) $PAGE->pagetype, 'local-remotesupport') === 0) {
        return '';
    }

    try {
        $session = \local_remotesupport\local\session_manager::get_active_session_for_student((int) $USER->id);
        if ($session) {
            $teacher = core_user::get_user($session->teacherid);
            $capturemode = get_config('local_remotesupport', 'capturemode');
            $cursorsamplems = (int) get_config('local_remotesupport', 'cursorsamplems');
            $PAGE->requires->js_call_amd('local_remotesupport/event_capture', 'init', [
                $session->id,
                fullname($teacher),
                $capturemode !== 'fullpage' ? 'main' : 'fullpage',
                (int) $USER->id,
                $cursorsamplems > 0 ? $cursorsamplems : 500,
            ]);
            return '';
        }

        return local_remotesupport_render_floating_request_button((int) $USER->id);
    } catch (\dml_exception $e) {
        return '';
    }
}

/**
 * Builds the floating request button shown to a student on every page
 * while they have no active session, in one of three states:
 *
 * - An open (requested/accepted) request already exists somewhere: the
 *   button links straight to that course's request page instead of
 *   offering to create a duplicate one.
 * - No open request, exactly one course qualifies: a direct link.
 * - No open request, several courses qualify: a plain <details> picker
 *   listing them, avoiding the need for any extra JavaScript.
 *
 * Returns '' whenever the student has no course where they could request
 * assistance, following the same "disappear entirely" rule already used
 * for the navbar pending-requests icon (see docs/decisions.md).
 *
 * @param int $studentid
 * @return string HTML, or '' if there is nothing to show.
 */
function local_remotesupport_render_floating_request_button(int $studentid): string {
    global $OUTPUT;

    $open = \local_remotesupport\local\session_manager::get_open_request_for_student_global($studentid);
    if ($open) {
        return $OUTPUT->render_from_template('local_remotesupport/floating_request_button', [
            'label' => get_string('button_viewmyrequest', 'local_remotesupport'),
            'url' => (new moodle_url('/local/remotesupport/request.php', ['id' => $open->courseid]))->out(false),
            'hascourses' => false,
        ]);
    }

    $courses = get_user_capability_course('local/remotesupport:requestassistance', $studentid, false, 'fullname');
    if (!$courses) {
        return '';
    }

    // Only the two branches that actually lead to *creating* a request
    // carry 'fromurl' — the "open request already exists" branch above
    // just views it, no new session_manager::create_request() call
    // involved, so there is nothing for that value to be stored against.
    $fromurl = local_remotesupport_current_page_local_url();

    if (count($courses) === 1) {
        $course = reset($courses);
        return $OUTPUT->render_from_template('local_remotesupport/floating_request_button', [
            'label' => get_string('button_requestassistance', 'local_remotesupport'),
            'url' => (new moodle_url('/local/remotesupport/request.php', [
                'id' => $course->id, 'fromurl' => $fromurl,
            ]))->out(false),
            'hascourses' => false,
        ]);
    }

    core_collator::asort_objects_by_property($courses, 'fullname');
    return $OUTPUT->render_from_template('local_remotesupport/floating_request_button', [
        'label' => get_string('button_requestassistance', 'local_remotesupport'),
        'hascourses' => true,
        'courses' => array_map(fn($c) => [
            'url' => (new moodle_url('/local/remotesupport/request.php', [
                'id' => $c->id, 'fromurl' => $fromurl,
            ]))->out(false),
            'fullname' => format_string($c->fullname, true, ['context' => context_course::instance($c->id)]),
        ], array_values($courses)),
    ]);
}

/**
 * Adds an icon to the navbar, next to the messages/notifications icons,
 * for any user able to provide assistance in at least one course. Clicking
 * it goes straight to view.php — a plain link, not a dropdown, unlike the
 * notification bell this sits next to (see docs/decisions.md for why).
 *
 * Unlike its first version, this is now always shown to that audience
 * (not only while a request is pending): view.php is also where the
 * teacher reaches their personal settings (e.g. enabling/disabling
 * support), so the icon needs to be reachable even with nothing pending.
 * The pending count still only appears as a badge when it is non-zero.
 *
 * The icon itself also reflects the viewer's own support-enabled
 * preference (teacher_settings), with a diagonal slash and a different
 * label when they have switched themselves off — a reminder that is
 * otherwise easy to forget about after leaving the settings page.
 *
 * Moodle discovers this automatically via the standard
 * PLUGIN_render_navbar_output naming convention
 * (core_renderer::navbar_plugin_output()), the same mechanism
 * message_popup uses for the notification bell itself. Runs on every page
 * for every logged-in user, so the installed/guest/login checks must stay
 * first and cheap; the badge count query is the same one view.php already
 * runs to build its own list, so the two can never disagree.
 *
 * Like before_footer() above, this checks local_remotesupport_is_installed()
 * first and catches dml_exception around the actual table access, so
 * leftover code from an incomplete uninstall degrades to "no icon" instead
 * of breaking every page's navbar.
 *
 * Also loads amd/src/navbar_badge.js, which polls
 * local_remotesupport_get_pending_count every 15 s and re-renders this same
 * template client-side, so the badge updates without a full page reload —
 * a deliberate reversal of the original "no live polling" decision for this
 * icon (see docs/decisions.md), requested so the whole request/accept
 * lifecycle feels reactive, not just the in-session screen sharing.
 *
 * @param \renderer_base $renderer
 * @return string HTML for the navbar, or '' if there is nothing to show.
 */
function local_remotesupport_render_navbar_output(\renderer_base $renderer): string {
    global $USER, $PAGE;

    if (!local_remotesupport_is_installed()) {
        return '';
    }
    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $courses = get_user_capability_course('local/remotesupport:provideassistance', (int) $USER->id, false);
    if (!$courses) {
        return '';
    }

    try {
        $pending = \local_remotesupport\local\session_manager::get_pending_requests_for_teacher((int) $USER->id);
    } catch (\dml_exception $e) {
        return '';
    }
    $supportenabled = \local_remotesupport\local\teacher_settings::is_support_enabled((int) $USER->id);

    $PAGE->requires->js_call_amd('local_remotesupport/navbar_badge', 'init');

    $html = $renderer->render_from_template('local_remotesupport/navbar_requests', [
        'count' => count($pending),
        'haspending' => (bool) $pending,
        'supportenabled' => $supportenabled,
        'url' => (new moodle_url('/local/remotesupport/view.php'))->out(false),
    ]);

    return '<div id="local-remotesupport-navbar-requests">' . $html . '</div>';
}
