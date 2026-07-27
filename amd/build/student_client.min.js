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
 * Student side of request.php: polls the request/session status and
 * re-renders the same student_page template client-side, so accepting a
 * request or a teacher entering the session is reflected without the
 * student having to reload the page. The plain PHP form/links rendered by
 * request.php are left untouched and still work if this module fails to
 * load or JavaScript is disabled — this only intercepts them.
 *
 * @module     local_remotesupport/student_client
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/templates', 'core/notification', 'local_remotesupport/session_requests'],
    function(Templates, Notification, SessionRequests) {

    var POLL_INTERVAL_MS = 4000;

    /**
     * @param {Number} courseid
     */
    var init = function(courseid) {
        var container = document.getElementById('local-remotesupport-student-panel');
        if (!container) {
            return;
        }

        var currentStatus = null;
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
            currentStatus = data;
            return Templates.renderForPromise('local_remotesupport/student_page', data).then(function(result) {
                Templates.replaceNodeContents(container, result.html, result.js);
                return null;
            });
        };

        var refresh = function() {
            return SessionRequests.getStudentStatus(courseid).then(render).catch(function() {
                // Transient errors ignored; the next poll tick will retry naturally.
            });
        };

        container.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form.classList.contains('local-remotesupport-requestform')) {
                return;
            }
            e.preventDefault();
            var reasonField = form.querySelector('[name="reason"]');
            SessionRequests.requestAssistance(courseid, reasonField ? reasonField.value : '')
                .then(refresh)
                .catch(Notification.exception);
        });

        container.addEventListener('click', function(e) {
            var link = e.target.closest('a');
            if (!link || !currentStatus || !currentStatus.sessionid) {
                return;
            }
            var href = link.getAttribute('href') || '';

            if (href.indexOf('action=cancel') !== -1) {
                e.preventDefault();
                SessionRequests.cancelRequest(currentStatus.sessionid).then(refresh).catch(Notification.exception);
            } else if (href.indexOf('action=enter') !== -1) {
                e.preventDefault();
                stopPolling();
                SessionRequests.enterSession(currentStatus.sessionid).then(function(result) {
                    window.location.href = result.redirecturl;
                }).catch(function(error) {
                    startPolling();
                    Notification.exception(error);
                });
            } else if (href.indexOf('action=finish') !== -1) {
                e.preventDefault();
                SessionRequests.finishSession(currentStatus.sessionid).then(refresh).catch(Notification.exception);
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
