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
 * student's own scroll position and mouse cursor, and marking where the
 * student clicks (visually, and optionally with a sound — see the mute
 * button and local_remotesupport/clicksound). Purely passive viewing: the
 * teacher cannot act on the student's page, click anything in it, or
 * scroll it manually.
 * A button lets the teacher toggle the whole player into the browser's
 * native fullscreen mode for a larger view; the reconstruction rescales to
 * fit on entering/leaving it, same logic as a window resize.
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
 * Also shows a floating text chat (local_remotesupport/chat_widget),
 * hidden automatically while in fullscreen — position:fixed nested inside a
 * :fullscreen element proved unreliable for click hit-testing in at least
 * one browser, so the chat is simply unavailable there rather than chasing
 * that further; see chat_widget.js's hide()/show().
 *
 * The actual sandboxed-iframe reconstruction (viewport sizing, scroll
 * simulation, srcdoc building) lives in local_remotesupport/screen_renderer,
 * shared with session_replay.js — this module only owns polling, the
 * connection indicator and the fullscreen button.
 *
 * @module     local_remotesupport/event_player
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    ['core/str', 'local_remotesupport/transport', 'local_remotesupport/chat_widget', 'local_remotesupport/screen_renderer'],
    function(Str, Transport, ChatWidget, ScreenRenderer) {

    var POLL_INTERVAL_MS = 2000;
    var CONNECTION_LOST_AFTER_MS = 8000;

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
     * @param {Number} ownuserid Current user's (teacher's) id, passed to the chat widget.
     * @param {String} studentname Display name of the alumno, for the chat panel heading.
     * @param {Boolean} clicksounddefault Initial state of the click-sound mute
     *                                    toggle, from local_remotesupport/clicksound —
     *                                    the teacher can still change it for this viewing only.
     */
    var init = function(sessionid, ownuserid, studentname, clicksounddefault) {
        var container = document.getElementById('local-remotesupport-player');
        if (!container) {
            return;
        }

        var chat = ChatWidget.init(sessionid, ownuserid, studentname);
        var firstPollDone = false;

        var indicator = document.createElement('div');
        indicator.className = 'local-remotesupport-connection-indicator';
        container.appendChild(indicator);

        var pageInfo = document.createElement('div');
        pageInfo.className = 'local-remotesupport-pageinfo';
        container.appendChild(pageInfo);

        // Hidden entirely on browsers without the Fullscreen API rather than
        // left as a dead button.
        var fullscreenButton = document.createElement('button');
        fullscreenButton.type = 'button';
        fullscreenButton.className = 'btn btn-outline-secondary btn-sm local-remotesupport-fullscreen-btn';
        if (!container.requestFullscreen) {
            fullscreenButton.style.display = 'none';
        }
        container.appendChild(fullscreenButton);

        // Starts from the site-wide default (local_remotesupport/clicksound)
        // but only changes it for this one viewing — never written back to
        // the server, see the module doc comment.
        var soundEnabled = !!clicksounddefault;
        var soundButton = document.createElement('button');
        soundButton.type = 'button';
        soundButton.className = 'btn btn-outline-secondary btn-sm local-remotesupport-soundtoggle-btn';
        container.appendChild(soundButton);

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
        var renderer = ScreenRenderer.create(iframe, viewportWrapper);

        Str.get_strings([
            {key: 'connection_connected', component: 'local_remotesupport'},
            {key: 'connection_waiting', component: 'local_remotesupport'},
            {key: 'connection_lost', component: 'local_remotesupport'},
            {key: 'sessionclosed', component: 'local_remotesupport'},
            {key: 'sessionendedbystudent', component: 'local_remotesupport'},
            {key: 'link_backtorequests', component: 'local_remotesupport'},
            {key: 'button_fullscreen', component: 'local_remotesupport'},
            {key: 'button_exitfullscreen', component: 'local_remotesupport'},
            {key: 'button_mutesound', component: 'local_remotesupport'},
            {key: 'button_unmutesound', component: 'local_remotesupport'}
        ]).then(function(strings) {
            var setState = function(state, label) {
                indicator.textContent = label;
                indicator.className = 'local-remotesupport-connection-indicator local-remotesupport-connection-' + state;
            };

            var updateSoundButton = function() {
                soundButton.textContent = soundEnabled ? strings[8] : strings[9];
            };
            updateSoundButton();
            soundButton.addEventListener('click', function() {
                soundEnabled = !soundEnabled;
                updateSoundButton();
            });

            fullscreenButton.textContent = strings[6];
            fullscreenButton.addEventListener('click', function() {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else {
                    container.requestFullscreen();
                }
            });
            // Also fires on Esc (the browser's own way out of fullscreen),
            // so the label, the rescaled size, and the chat's visibility
            // all stay correct either way.
            document.addEventListener('fullscreenchange', function() {
                var isFullscreen = document.fullscreenElement === container;
                fullscreenButton.textContent = isFullscreen ? strings[7] : strings[6];
                if (isFullscreen) {
                    chat.hide();
                } else {
                    chat.show();
                }
                renderer.reapplyViewportSize();
            });

            var applyEvent = function(event, isReplay) {
                var payload = decodePayload(event);
                if (!payload) {
                    return;
                }
                if (event.eventtype === 'chat_message') {
                    chat.receive(event, isReplay);
                    return;
                }
                if (event.eventtype === 'page' && typeof payload.html === 'string') {
                    renderer.renderPage(payload, pageInfo);
                } else if (event.eventtype === 'scroll') {
                    renderer.applyScrollPosition(payload.x, payload.y);
                } else if (event.eventtype === 'cursor') {
                    renderer.applyCursorPosition(payload.x, payload.y, payload.hover, payload.typing);
                } else if (event.eventtype === 'student_click') {
                    // Suppressed on the very first poll (isReplay): those are
                    // events that queued up before this viewer started
                    // polling, not clicks happening right now — showing/
                    // playing a burst of them at once would be confusing
                    // noise, not a useful signal. Same reasoning chat_widget.js
                    // already applies to its own unread notification.
                    if (!isReplay) {
                        renderer.showClickMark(payload.x, payload.y);
                        if (soundEnabled) {
                            renderer.playClickSound();
                        }
                    }
                }
            };

            var stopPolling = function(label) {
                if (pollHandle !== null) {
                    window.clearInterval(pollHandle);
                    pollHandle = null;
                }
                setState('ended', label);
                chat.destroy();
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
                    var isReplay = !firstPollDone;
                    firstPollDone = true;
                    events.forEach(function(event) {
                        applyEvent(event, isReplay);
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
