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
 * page snapshot inside a script-disabled sandboxed iframe. Also lets the
 * teacher point (move the mouse over the frame), highlight (click an
 * element in the frame), and scroll the frame to drive the student's own
 * scroll position (scroll_request, requires 'pointer' level, same as
 * cursor/highlight) — all without performing any content-changing action
 * for the student.
 *
 * The iframe uses sandbox="allow-same-origin" with no allow-scripts token:
 * this is what lets this module call contentWindow.scrollTo() and attach
 * listeners to contentDocument on it (an opaque/null-origin frame would
 * reject that cross-origin access), while still fully disabling script
 * execution inside the frame regardless of origin — sandboxing scripting
 * off is unconditional once allow-scripts is absent. The server-side
 * sanitizer (html_sanitizer.php) is still the authoritative content
 * cleaner; this is a second, independent layer.
 *
 * @module     local_remotesupport/event_player
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str', 'local_remotesupport/transport'], function(Str, Transport) {

    var POLL_INTERVAL_MS = 2000;
    var CONNECTION_LOST_AFTER_MS = 8000;
    var CURSOR_THROTTLE_MS = 60;
    var SCROLL_THROTTLE_MS = 300;
    var SUPPRESS_ECHO_MS = 50;
    var MAX_SELECTOR_DEPTH = 4;

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
     * @param {String} value
     * @return {String}
     */
    var escapeForSelector = function(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    };

    /**
     * Build a selector for an element, preferring the most stable option
     * available: a plugin-specific marker, then a stable id, then a
     * data-* attribute, then a depth-limited structural path, in that
     * order. Returns null if nothing usable was found.
     *
     * @param {Element} el
     * @return {String|null}
     */
    var buildSelector = function(el) {
        if (!el || el.nodeType !== 1 || el === el.ownerDocument.body) {
            return null;
        }
        if (el.hasAttribute('data-remotesupport-id')) {
            return '[data-remotesupport-id="' + escapeForSelector(el.getAttribute('data-remotesupport-id')) + '"]';
        }
        if (el.id) {
            return '#' + escapeForSelector(el.id);
        }
        for (var i = 0; i < el.attributes.length; i++) {
            var attr = el.attributes[i];
            if (attr.name.indexOf('data-') === 0) {
                return '[' + attr.name + '="' + escapeForSelector(attr.value) + '"]';
            }
        }

        var path = [];
        var node = el;
        var depth = 0;
        while (node && node.nodeType === 1 && depth < MAX_SELECTOR_DEPTH) {
            if (node.id) {
                path.unshift('#' + escapeForSelector(node.id));
                return path.join(' > ');
            }
            var segment = node.tagName.toLowerCase();
            var parent = node.parentElement;
            if (parent) {
                var index = Array.prototype.indexOf.call(parent.children, node) + 1;
                segment += ':nth-child(' + index + ')';
            }
            path.unshift(segment);
            node = parent;
            depth++;
        }
        return path.length ? path.join(' > ') : null;
    };

    /**
     * @param {Function} fn
     * @param {Number} wait
     * @return {Function}
     */
    var throttle = function(fn, wait) {
        var last = 0;
        return function() {
            var now = Date.now();
            if (now - last >= wait) {
                last = now;
                fn.apply(null, arguments);
            }
        };
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

        var clickResultEl = document.createElement('div');
        clickResultEl.className = 'local-remotesupport-clickresult';
        container.appendChild(clickResultEl);

        var viewportWrapper = document.createElement('div');
        viewportWrapper.className = 'local-remotesupport-player-viewport';
        container.appendChild(viewportWrapper);

        var iframe = document.createElement('iframe');
        iframe.setAttribute('sandbox', 'allow-same-origin');
        iframe.className = 'local-remotesupport-player-frame';
        iframe.setAttribute('title', 'local_remotesupport player');
        viewportWrapper.appendChild(iframe);

        var clearButton = document.getElementById('local-remotesupport-clearhighlight');
        var requestClickButton = document.getElementById('local-remotesupport-requestclick');
        var inputValueField = document.getElementById('local-remotesupport-inputvalue');
        var setValueButton = document.getElementById('local-remotesupport-setvalue');
        var appendTextButton = document.getElementById('local-remotesupport-appendtext');
        var clearValueButton = document.getElementById('local-remotesupport-clearvalue');

        var sinceid = 0;
        var lastSuccessAt = Date.now();
        var pollHandle = null;
        var currentHighlight = null;
        // Set right before programmatically scrolling the iframe to follow
        // the alumno's own 'scroll' event, so the scroll listener added in
        // wireFrameInteraction() below does not immediately echo that same
        // position back as a scroll_request — see docs/decisions.md.
        var suppressOutgoingScroll = false;
        var lastViewport = null;

        // Forces the iframe to actually be the alumno's own reported
        // viewport size (not just visually similar), then shrinks it back
        // down with a CSS transform to fit the teacher's screen. Without
        // this, the remote cursor/highlight and click coordinates were
        // computed as a fraction of a differently-sized, differently
        // laid-out box — on a responsive Moodle theme that reflows at
        // different widths, the same fraction can land on completely
        // different content. See docs/decisions.md.
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

        Str.get_strings([
            {key: 'connection_connected', component: 'local_remotesupport'},
            {key: 'connection_waiting', component: 'local_remotesupport'},
            {key: 'connection_lost', component: 'local_remotesupport'},
            {key: 'sessionclosed', component: 'local_remotesupport'},
            {key: 'clickresult_clicked', component: 'local_remotesupport'},
            {key: 'clickresult_blocked', component: 'local_remotesupport'},
            {key: 'clickresult_declined', component: 'local_remotesupport'},
            {key: 'clickresult_notfound', component: 'local_remotesupport'},
            {key: 'clickresult_nohighlight', component: 'local_remotesupport'},
            {key: 'inputresult_applied', component: 'local_remotesupport'},
            {key: 'inputresult_blocked', component: 'local_remotesupport'},
            {key: 'inputresult_notfound', component: 'local_remotesupport'},
            {key: 'sessionendedbystudent', component: 'local_remotesupport'},
            {key: 'link_backtorequests', component: 'local_remotesupport'}
        ]).then(function(strings) {
            var clickResultLabels = {
                clicked: strings[4],
                blocked: strings[5],
                declined: strings[6],
                notfound: strings[7]
            };
            var inputResultLabels = {
                applied: strings[9],
                blocked: strings[10],
                notfound: strings[11]
            };
            var setState = function(state, label) {
                indicator.textContent = label;
                indicator.className = 'local-remotesupport-connection-indicator local-remotesupport-connection-' + state;
            };

            var pushHighlight = function(selector) {
                currentHighlight = selector;
                Transport.pushEvent(sessionid, 'highlight', {selector: selector}).catch(function() {
                    // Transient errors ignored; next click/poll will retry naturally.
                });
            };

            var applyLocalHighlightPreview = function(doc, selector) {
                doc.querySelectorAll('.local-remotesupport-teacher-highlight').forEach(function(el) {
                    el.classList.remove('local-remotesupport-teacher-highlight');
                });
                if (selector) {
                    try {
                        var el = doc.querySelector(selector);
                        if (el) {
                            el.classList.add('local-remotesupport-teacher-highlight');
                        }
                    } catch (e) {
                        // Invalid/unsupported selector: no local preview, event still sent.
                    }
                }
            };

            var wireFrameInteraction = function() {
                var doc = iframe.contentDocument;
                var win = iframe.contentWindow;
                if (!doc || !win) {
                    return;
                }

                var sendCursor = throttle(function(clientX, clientY) {
                    Transport.pushEvent(sessionid, 'cursor', {
                        x: clientX / win.innerWidth,
                        y: clientY / win.innerHeight
                    }).catch(function() {
                        // Transient errors ignored: cursor is a continuous stream.
                    });
                }, CURSOR_THROTTLE_MS);

                doc.addEventListener('mousemove', function(e) {
                    sendCursor(e.clientX, e.clientY);
                });

                var sendScrollRequest = throttle(function() {
                    if (suppressOutgoingScroll) {
                        return;
                    }
                    Transport.pushEvent(sessionid, 'scroll_request', {
                        x: win.scrollX, y: win.scrollY
                    }).catch(function() {
                        // Transient errors ignored: the next scroll tick will retry naturally.
                    });
                }, SCROLL_THROTTLE_MS);

                win.addEventListener('scroll', sendScrollRequest, {passive: true});

                doc.addEventListener('click', function(e) {
                    e.preventDefault();
                    var selector = buildSelector(e.target);
                    if (!selector) {
                        return;
                    }
                    if (selector === currentHighlight) {
                        applyLocalHighlightPreview(doc, null);
                        pushHighlight(null);
                        return;
                    }
                    applyLocalHighlightPreview(doc, selector);
                    pushHighlight(selector);
                }, true);

                if (currentHighlight) {
                    applyLocalHighlightPreview(doc, currentHighlight);
                }
            };

            if (clearButton) {
                clearButton.addEventListener('click', function() {
                    var doc = iframe.contentDocument;
                    if (doc) {
                        applyLocalHighlightPreview(doc, null);
                    }
                    pushHighlight(null);
                });
            }

            if (requestClickButton) {
                requestClickButton.addEventListener('click', function() {
                    if (!currentHighlight) {
                        clickResultEl.textContent = strings[8];
                        return;
                    }
                    clickResultEl.textContent = '';
                    Transport.pushEvent(sessionid, 'click_request', {selector: currentHighlight}).catch(function() {
                        // Transient errors ignored; the teacher can just try again.
                    });
                });
            }

            var requestInput = function(action) {
                if (!currentHighlight) {
                    clickResultEl.textContent = strings[8];
                    return;
                }
                clickResultEl.textContent = '';
                Transport.pushEvent(sessionid, 'input_request', {
                    selector: currentHighlight,
                    action: action,
                    value: inputValueField ? inputValueField.value : ''
                }).catch(function() {
                    // Transient errors ignored; the teacher can just try again.
                });
            };

            if (setValueButton) {
                setValueButton.addEventListener('click', function() {
                    requestInput('set_value');
                });
            }
            if (appendTextButton) {
                appendTextButton.addEventListener('click', function() {
                    requestInput('append_text');
                });
            }
            if (clearValueButton) {
                clearValueButton.addEventListener('click', function() {
                    requestInput('clear');
                });
            }

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
                        wireFrameInteraction();
                        if (pendingScroll && iframe.contentWindow) {
                            try {
                                iframe.contentWindow.scrollTo(pendingScroll.x, pendingScroll.y);
                            } catch (e) {
                                // Best-effort scroll sync only.
                            }
                        }
                    };
                    iframe.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8">' +
                        '<base href="' + M.cfg.wwwroot + '/">' + links + '</head><body>' +
                        payload.html + modalHtml + '</body></html>';
                } else if (event.eventtype === 'scroll' && iframe.contentWindow) {
                    try {
                        suppressOutgoingScroll = true;
                        iframe.contentWindow.scrollTo(payload.x, payload.y);
                        window.setTimeout(function() {
                            suppressOutgoingScroll = false;
                        }, SUPPRESS_ECHO_MS);
                    } catch (e) {
                        // Best-effort scroll sync only.
                    }
                } else if (event.eventtype === 'click_result') {
                    clickResultEl.textContent = clickResultLabels[payload.outcome] || '';
                } else if (event.eventtype === 'input_result') {
                    clickResultEl.textContent = inputResultLabels[payload.outcome] || '';
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
                backlink.textContent = strings[13];
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
                        showSessionEndedOverlay(strings[12]);
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
