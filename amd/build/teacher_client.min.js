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
 * Teacher side of view.php: polls the pending-requests/open-sessions lists
 * and re-renders the same teacher_dashboard template client-side, so a new
 * request or a status change is reflected without the teacher having to
 * reload the page. The plain PHP links rendered by view.php are left
 * untouched and still work if this module fails to load or JavaScript is
 * disabled — this only intercepts them.
 *
 * Rows are re-rendered as a whole on every poll (no per-row id on the
 * markup), so the session id for a clicked action is read back out of the
 * href Mustache already put there, rather than adding data-* attributes to
 * the template.
 *
 * @module     local_remotesupport/teacher_client
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/templates', 'core/notification', 'local_remotesupport/session_requests'],
    function(Templates, Notification, SessionRequests) {

    var POLL_INTERVAL_MS = 4000;

    /**
     * @param {String} href
     * @return {Number} 0 if not present/parseable.
     */
    var sessionIdFromHref = function(href) {
        try {
            var parsed = new URL(href, window.location.href);
            var raw = parsed.searchParams.get('sessionid');
            return raw ? parseInt(raw, 10) : 0;
        } catch (e) {
            return 0;
        }
    };

    var init = function() {
        var container = document.getElementById('local-remotesupport-teacher-dashboard');
        if (!container) {
            return;
        }

        var pollHandle = null;

        var startPolling = function() {
            if (pollHandle === null) {
                pollHandle = window.setInterval(refresh, POLL_INTERVAL_MS);
            }
        };

        var stopPolling = function() {
            if (pollHandle !== null) {
                window.clearInterval(pollHandle);
                pollHandle = null;
            }
        };

        var render = function(data) {
            return Templates.renderForPromise('local_remotesupport/teacher_dashboard', data).then(function(result) {
                Templates.replaceNodeContents(container, result.html, result.js);
                return null;
            });
        };

        var refresh = function() {
            return SessionRequests.getTeacherDashboard().then(render).catch(function() {
                // Transient errors ignored; the next poll tick will retry naturally.
            });
        };

        container.addEventListener('click', function(e) {
            var link = e.target.closest('a');
            if (!link) {
                return;
            }
            var href = link.getAttribute('href') || '';
            var sessionid = sessionIdFromHref(href);
            if (!sessionid) {
                return;
            }

            if (href.indexOf('action=accept') !== -1) {
                e.preventDefault();
                stopPolling();
                SessionRequests.acceptRequest(sessionid).then(function(result) {
                    window.location.href = result.redirecturl;
                }).catch(function(error) {
                    startPolling();
                    Notification.exception(error);
                });
            } else if (href.indexOf('action=enter') !== -1) {
                e.preventDefault();
                stopPolling();
                SessionRequests.enterSession(sessionid).then(function(result) {
                    window.location.href = result.redirecturl;
                }).catch(function(error) {
                    startPolling();
                    Notification.exception(error);
                });
            } else if (href.indexOf('action=finish') !== -1) {
                e.preventDefault();
                SessionRequests.finishSession(sessionid).then(refresh).catch(Notification.exception);
            }
        });

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopPolling();
            } else {
                refresh();
                startPolling();
            }
        });

        refresh();
        startPolling();
    };

    return {
        init: init
    };
});
