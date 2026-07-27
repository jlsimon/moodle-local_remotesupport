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
 * Keeps the navbar pending-requests badge (navbar_requests.mustache, see
 * lib.php's local_remotesupport_render_navbar_output()) up to date without a
 * full page reload. Loaded on every Moodle page for any user able to provide
 * assistance in at least one course — deliberately the lightest poll in the
 * plugin (15 s, no per-row data, a single count), since it runs sitewide
 * rather than only on the plugin's own pages.
 *
 * @module     local_remotesupport/navbar_badge
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/templates', 'local_remotesupport/session_requests'], function(Templates, SessionRequests) {

    var POLL_INTERVAL_MS = 15000;

    var init = function() {
        var container = document.getElementById('local-remotesupport-navbar-requests');
        if (!container) {
            return;
        }

        // The link target never changes (always view.php); read it once from
        // the server-rendered markup instead of hardcoding the url here.
        var link = container.querySelector('a');
        var url = link ? link.getAttribute('href') : '';

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

        var refresh = function() {
            return SessionRequests.getPendingCount().then(function(result) {
                return Templates.renderForPromise('local_remotesupport/navbar_requests', {
                    count: result.count,
                    haspending: result.count > 0,
                    supportenabled: result.supportenabled,
                    url: url
                });
            }).then(function(result) {
                Templates.replaceNodeContents(container, result.html, result.js);
                return null;
            }).catch(function() {
                // Transient errors ignored; the next poll tick will retry naturally.
            });
        };

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopPolling();
            } else {
                refresh();
                startPolling();
            }
        });

        startPolling();
    };

    return {
        init: init
    };
});
