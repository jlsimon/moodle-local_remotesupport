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
 * Teacher-side player: polls for capture events and renders the latest
 * page snapshot inside a script-disabled sandboxed iframe, following the
 * student's own scroll position. Purely passive viewing: the teacher cannot
 * act on the student's page, click anything in it, or scroll it manually.
 *
 * The captured content's own document has no scrollable overflow at all
 * (html/body forced to `overflow: hidden`) — its "scroll position" is
 * simulated by CSS-translating an inner wrapper div, driven only by synced
 * 'page'/'scroll' events. This is deliberate: an earlier attempt relied on
 * the iframe's native `contentWindow.scrollTo()` plus blocking 'wheel'/
 * keyboard events to stop the profesor scrolling it manually, but wheel
 * scrolling of an iframe can bypass JS event handlers entirely in some
 * browsers (a separate compositor-thread scroll path). With no scrollable
 * box to begin with, there is nothing for any input device to move.
 *
 * The iframe uses sandbox="allow-same-origin" with no allow-scripts token:
 * this is what lets this module reach into contentDocument (an opaque/
 * null-origin frame would reject that cross-origin access), while still
 * fully disabling script execution inside the frame regardless of origin —
 * sandboxing scripting off is unconditional once allow-scripts is absent.
 * The server-side sanitizer (html_sanitizer.php) is still the authoritative
 * content cleaner; this is a second, independent layer.
 *
 * @module     local_remotesupport/event_player
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str', 'local_remotesupport/transport'], function(Str, Transport) {

    var POLL_INTERVAL_MS = 2000;
    var CONNECTION_LOST_AFTER_MS = 8000;
    var VIEWPORT_CONTENT_ID = 'local-remotesupport-viewport-content';

    /**
     * @param {Object} event
     * @return {Object|null}
     */
    var decodePayload = function(event) {
        try {
            return JSON.parse(event.payload);
        } catch (e) {
            return null;
        }
    };

    /**
     * @param {Number} sessionid
     */
    var init = function(sessionid) {
        var container = document.getElementById('local-remotesupport-player');
        if (!container) {
            return;
        }

        var indicator = document.createElement('div');
        indicator.className = 'local-remotesupport-connection-indicator';
        container.appendChild(indicator);

        var pageInfo = document.createElement('div');
        pageInfo.className = 'local-remotesupport-pageinfo';
        container.appendChild(pageInfo);

        var viewportWrapper = document.createElement('div');
        viewportWrapper.className = 'local-remotesupport-player-viewport';
        container.appendChild(viewportWrapper);

        var iframe = document.createElement('iframe');
        iframe.setAttribute('sandbox', 'allow-same-origin');
        iframe.className = 'local-remotesupport-player-frame';
        iframe.setAttribute('title', 'local_remotesupport player');
        viewportWrapper.appendChild(iframe);

        var sinceid = 0;
        var lastSuccessAt = Date.now();
        var pollHandle = null;
        var lastViewport = null;

        // Forces the iframe to actually be the alumno's own reported
        // viewport size (not just visually similar), then shrinks it back
        // down with a CSS transform to fit the teacher's screen. On a
        // responsive Moodle theme that reflows at different widths, a
        // differently-sized frame would render the same content laid out
        // differently than the alumno actually sees it. See docs/decisions.md.
        var applyViewportSize = function(viewport) {
            if (!viewport || typeof viewport.width !== 'number' || typeof viewport.height !== 'number' ||
                    viewport.width <= 0 || viewport.height <= 0) {
                return;
            }
            var width = Math.min(Math.max(viewport.width, 200), 4000);
            var height = Math.min(Math.max(viewport.height, 200), 4000);
            lastViewport = {width: width, height: height};

            var availableWidth = viewportWrapper.clientWidth || width;
            var scale = Math.min(1, availableWidth / width);

            iframe.style.width = width + 'px';
            iframe.style.height = height + 'px';
            iframe.style.transform = 'scale(' + scale + ')';
            viewportWrapper.style.height = (height * scale) + 'px';
        };

        window.addEventListener('resize', function() {
            if (lastViewport) {
                applyViewportSize(lastViewport);
            }
        });

        // Positions the captured content by CSS-translating the wrapper div
        // instead of using native document scrolling — see the module
        // doc comment above for why. `x`/`y` are the alumno's real
        // document-coordinate scroll position (Fase 4 payload, unchanged).
        var applyScrollPosition = function(x, y) {
            var doc = iframe.contentDocument;
            var content = doc && doc.getElementById(VIEWPORT_CONTENT_ID);
            if (content && typeof x === 'number' && typeof y === 'number') {
                content.style.transform = 'translate(' + (-x) + 'px, ' + (-y) + 'px)';
            }
        };

        Str.get_strings([
            {key: 'connection_connected', component: 'local_remotesupport'},
            {key: 'connection_waiting', component: 'local_remotesupport'},
            {key: 'connection_lost', component: 'local_remotesupport'},
            {key: 'sessionclosed', component: 'local_remotesupport'},
            {key: 'sessionendedbystudent', component: 'local_remotesupport'},
            {key: 'link_backtorequests', component: 'local_remotesupport'}
        ]).then(function(strings) {
            var setState = function(state, label) {
                indicator.textContent = label;
                indicator.className = 'local-remotesupport-connection-indicator local-remotesupport-connection-' + state;
            };

            var applyEvent = function(event) {
                var payload = decodePayload(event);
                if (!payload) {
                    return;
                }
                if (event.eventtype === 'page' && typeof payload.html === 'string') {
                    pageInfo.textContent = (payload.title || '') + ' — ' + (payload.url || '');
                    applyViewportSize(payload.viewport);

                    var links = (Array.isArray(payload.css) ? payload.css : [])
                        .filter(function(href) {
                            return typeof href === 'string' && href.indexOf(M.cfg.wwwroot) === 0;
                        })
                        .map(function(href) {
                            return '<link rel="stylesheet" href="' + href.replace(/"/g, '&quot;') + '">';
                        })
                        .join('');

                    var modalHtml = typeof payload.modal === 'string' ? payload.modal : '';

                    var pendingScroll = payload.scroll || null;
                    iframe.onload = function() {
                        if (pendingScroll) {
                            applyScrollPosition(pendingScroll.x, pendingScroll.y);
                        }
                    };
                    // html/body get no scrollable overflow of their own — see
                    // the module doc comment. The modal stays outside the
                    // translated wrapper so it keeps behaving like a normal
                    // position:fixed overlay instead of moving with the scroll
                    // simulation (a CSS transform on an ancestor would
                    // otherwise turn it into the containing block for
                    // fixed-position descendants).
                    iframe.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8">' +
                        '<base href="' + M.cfg.wwwroot + '/">' + links +
                        '<style>html,body{margin:0;overflow:hidden;height:100%;}</style>' +
                        '</head><body>' +
                        '<div id="' + VIEWPORT_CONTENT_ID + '">' + payload.html + '</div>' +
                        modalHtml + '</body></html>';
                } else if (event.eventtype === 'scroll') {
                    applyScrollPosition(payload.x, payload.y);
                }
            };

            var stopPolling = function(label) {
                if (pollHandle !== null) {
                    window.clearInterval(pollHandle);
                    pollHandle = null;
                }
                setState('ended', label);
            };

            // The connection indicator badge is easy to miss while looking
            // at the reconstruction itself, so ending the session (from the
            // alumno's side — the teacher's own "Finalizar" link already
            // navigates them away, so reaching this poll error means someone
            // else closed it) also gets a prominent, impossible-to-miss
            // panel with a way back to the request list. A browser tab that
            // was reached via a normal link/redirect (not window.open())
            // cannot be closed from script, so this is the practical
            // equivalent requested: a clear message plus a one-click way out.
            var showSessionEndedOverlay = function(message) {
                var overlay = document.createElement('div');
                overlay.className = 'local-remotesupport-sessionended';
                overlay.setAttribute('role', 'alert');

                var text = document.createElement('p');
                text.textContent = message;
                overlay.appendChild(text);

                var backlink = document.createElement('a');
                backlink.className = 'btn btn-primary btn-sm';
                backlink.href = M.cfg.wwwroot + '/local/remotesupport/view.php';
                backlink.textContent = strings[5];
                overlay.appendChild(backlink);

                container.appendChild(overlay);
            };

            // Tracks whether the connection was ever marked "lost" since the
            // last successful poll, so that recovering from a gap can ask
            // the alumno for a full resync instead of waiting up to
            // PAGE_HEARTBEAT_MS for the next periodic snapshot — this is
            // the "solicitar reconstrucción completa si faltan eventos"
            // requirement: a real gap happens when the purge_events task
            // deletes unconsumed events during a long disconnect.
            var wasDisconnected = false;

            var poll = function() {
                Transport.pullEvents(sessionid, sinceid).then(function(events) {
                    lastSuccessAt = Date.now();
                    setState('connected', strings[0]);
                    if (wasDisconnected) {
                        wasDisconnected = false;
                        Transport.pushEvent(sessionid, 'resync_request', {}).catch(function() {
                            // Non-fatal: the next heartbeat will still resync eventually.
                        });
                    }
                    events.forEach(function(event) {
                        applyEvent(event);
                        if (event.id > sinceid) {
                            sinceid = event.id;
                        }
                    });
                    return null;
                }).catch(function(error) {
                    if (error && error.errorcode === 'errorsessionnotactive') {
                        stopPolling(strings[3]);
                        showSessionEndedOverlay(strings[4]);
                        return;
                    }
                    if (Date.now() - lastSuccessAt > CONNECTION_LOST_AFTER_MS) {
                        wasDisconnected = true;
                        setState('lost', strings[2]);
                    }
                });
            };

            setState('waiting', strings[1]);
            poll();
            pollHandle = window.setInterval(poll, POLL_INTERVAL_MS);
            return null;
        }).catch(function() {
            // If strings fail to load, polling still proceeds without localized labels.
        });
    };

    return {
        init: init
    };
});
