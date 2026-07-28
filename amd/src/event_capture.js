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
 * Student-side screen capture: builds sanitized page snapshots and pushes
 * them for the teacher to see, and shows the persistent "assistance active"
 * status bar with a finish button. View-only: the teacher can watch the
 * student's navigation and scroll position but cannot act on the student's
 * page in any way. Loaded on every Moodle page while the student has an
 * active session (see lib.php::local_remotesupport_before_footer()).
 *
 * Only structure is ever captured, never form field values: input/textarea
 * values are stripped client-side before sending, and stripped again
 * authoritatively on the server regardless of what this code does.
 *
 * @module     local_remotesupport/event_capture
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    ['jquery', 'core/str', 'local_remotesupport/transport'],
    function($, Str, Transport) {

    var MAIN_CONTENT_SELECTORS = ['#region-main', 'main[role="main"]', 'main', 'body'];
    var MODAL_SELECTOR = '.modal.show, .modal.in, [aria-modal="true"]';
    var BLOCKED_TAGS = ['script', 'iframe', 'object', 'embed', 'applet', 'noscript', 'link', 'meta'];
    var OWN_CLASS_PREFIX = 'local-remotesupport-';
    var PAGE_HEARTBEAT_MS = 10000;
    var PAGE_DEBOUNCE_MS = 1500;
    var SCROLL_THROTTLE_MS = 300;
    var MAX_HTML_LENGTH = 150000;
    var MAX_FULLPAGE_HTML_LENGTH = 400000;
    var MAX_MODAL_HTML_LENGTH = 30000;
    var INCOMING_POLL_INTERVAL_MS = 500;

    /**
     * @return {Element}
     */
    var findMainContent = function() {
        for (var i = 0; i < MAIN_CONTENT_SELECTORS.length; i++) {
            var el = document.querySelector(MAIN_CONTENT_SELECTORS[i]);
            if (el) {
                return el;
            }
        }
        return document.body;
    };

    /**
     * Root element to capture/observe, per the site's configured capture
     * mode (local_remotesupport/capturemode, see lib.php). 'fullpage'
     * captures the whole document (navigation, blocks, footer included),
     * as close as possible to what the student actually sees; 'main' (the
     * default) keeps the original Fase 2 scope.
     *
     * @param {String} mode 'main' or 'fullpage'
     * @return {Element}
     */
    var findCaptureRoot = function(mode) {
        return mode === 'fullpage' ? document.body : findMainContent();
    };

    /**
     * Best-effort client-side cleanup, mirroring (not replacing) the
     * authoritative server-side sanitizer. Mutates the clone in place.
     *
     * @param {Element} clone
     */
    var cleanClone = function(clone) {
        BLOCKED_TAGS.forEach(function(tag) {
            clone.querySelectorAll(tag).forEach(function(el) {
                el.parentNode.removeChild(el);
            });
        });

        clone.querySelectorAll('*').forEach(function(el) {
            for (var i = el.attributes.length - 1; i >= 0; i--) {
                var attr = el.attributes[i];
                if (attr.name.toLowerCase().indexOf('on') === 0) {
                    el.removeAttribute(attr.name);
                }
            }
        });

        clone.querySelectorAll('input').forEach(function(el) {
            el.removeAttribute('value');
        });
        clone.querySelectorAll('textarea').forEach(function(el) {
            el.textContent = '';
        });
    };

    /**
     * @param {Element} source
     * @param {Number} maxlength
     * @return {String}
     */
    var buildSanitizedHtml = function(source, maxlength) {
        var clone = source.cloneNode(true);
        cleanClone(clone);
        var html = clone.innerHTML || '';
        if (html.length > maxlength) {
            html = html.substring(0, maxlength) + '<!-- truncated -->';
        }
        return html;
    };

    /**
     * Captures the currently open Moodle modal (Bootstrap 4/5 markup), if
     * any, including its backdrop so it renders with the expected dimming.
     * Moodle appends modals as direct children of <body>, outside the
     * main content region, so this is captured separately from it.
     *
     * @return {String|null}
     */
    var captureOpenModal = function() {
        var modal = document.querySelector(MODAL_SELECTOR);
        if (!modal) {
            return null;
        }
        var backdrop = document.querySelector('.modal-backdrop');
        var html = '';
        if (backdrop) {
            var backdropclone = backdrop.cloneNode(true);
            cleanClone(backdropclone);
            html += backdropclone.outerHTML;
        }
        var modalclone = modal.cloneNode(true);
        cleanClone(modalclone);
        html += modalclone.outerHTML;

        if (html.length > MAX_MODAL_HTML_LENGTH) {
            html = html.substring(0, MAX_MODAL_HTML_LENGTH) + '<!-- truncated -->';
        }
        return html;
    };

    /**
     * Same-origin stylesheet URLs currently loaded, so the teacher's
     * reconstruction can load the real theme CSS instead of rendering
     * unstyled HTML.
     *
     * @return {String[]}
     */
    var collectSameOriginStylesheets = function() {
        return Array.prototype.slice.call(document.styleSheets)
            .map(function(sheet) {
                try {
                    return sheet.href;
                } catch (e) {
                    return null;
                }
            })
            .filter(function(href) {
                return href && href.indexOf(M.cfg.wwwroot) === 0;
            });
    };

    /**
     * @param {String} mode 'main' or 'fullpage'
     * @return {Object}
     */
    var buildPageSnapshot = function(mode) {
        var isFullPage = mode === 'fullpage';
        return {
            url: location.pathname + location.search,
            title: document.title,
            html: buildSanitizedHtml(findCaptureRoot(mode), isFullPage ? MAX_FULLPAGE_HTML_LENGTH : MAX_HTML_LENGTH),
            // In 'fullpage' mode the modal, like the rest of <body>, is
            // already part of the captured html above; capturing it a
            // second time here would just duplicate it in the payload.
            modal: isFullPage ? null : captureOpenModal(),
            css: collectSameOriginStylesheets(),
            viewport: {width: window.innerWidth, height: window.innerHeight},
            scroll: {x: window.scrollX, y: window.scrollY}
        };
    };

    /**
     * @param {Function} fn
     * @param {Number} wait
     * @return {Function}
     */
    var debounce = function(fn, wait) {
        var timer = null;
        return function() {
            window.clearTimeout(timer);
            timer = window.setTimeout(fn, wait);
        };
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
                fn();
            }
        };
    };

    /**
     * @param {Number} sessionid
     * @param {String} teachername
     * @param {String} capturemode 'main' (default) or 'fullpage'
     */
    var init = function(sessionid, teachername, capturemode) {
        var mode = capturemode === 'fullpage' ? 'fullpage' : 'main';
        var statusbar = null;

        var sendPageSnapshot = function() {
            Transport.pushEvent(sessionid, 'page', buildPageSnapshot(mode)).catch(function() {
                // Transient network/server errors are expected during navigation; ignored.
            });
        };
        var sendScroll = function() {
            Transport.pushEvent(sessionid, 'scroll', {x: window.scrollX, y: window.scrollY}).catch(function() {
                // Ignored, see above.
            });
        };
        var debouncedSnapshot = debounce(sendPageSnapshot, PAGE_DEBOUNCE_MS);

        // The status bar lives directly under <body> too; the observers
        // below must not mistake its own insertions/removals for
        // alumno-driven changes worth re-syncing over — that would just be a
        // spurious resend, not an infinite loop, but it is exactly the
        // local/remote conflation the spec asks to avoid. Checked via
        // closest(), not just the node's own class list, so it still catches
        // e.g. a button added deep inside the status bar while watching the
        // whole <body> in 'fullpage' mode — a case the original
        // direct-children-only check never had to handle, since in 'main'
        // mode the status bar never lives inside the observed main-content
        // root at all.
        var isOwnElement = function(node) {
            return node.nodeType === 1 && !!(node.closest && node.closest('[class*="' + OWN_CLASS_PREFIX + '"]'));
        };

        var hasRelevantMutation = function(mutations) {
            return mutations.some(function(mutation) {
                var nodes = Array.prototype.slice.call(mutation.addedNodes)
                    .concat(Array.prototype.slice.call(mutation.removedNodes));
                return nodes.some(function(node) {
                    return !isOwnElement(node);
                });
            });
        };

        var contentObserver = new MutationObserver(function(mutations) {
            if (hasRelevantMutation(mutations)) {
                debouncedSnapshot();
            }
        });
        // Moodle appends modals as direct children of <body>, outside the
        // main content region — in 'main' mode that needs a separate,
        // shallow observer. In 'fullpage' mode contentObserver above already
        // watches the whole <body> (subtree included), so modals are covered
        // as part of that and this second observer would just be redundant.
        var bodyObserver = mode === 'fullpage' ? null : new MutationObserver(function(mutations) {
            if (hasRelevantMutation(mutations)) {
                debouncedSnapshot();
            }
        });

        var cleanup = function() {
            if (statusbar && statusbar.parentNode) {
                statusbar.parentNode.removeChild(statusbar);
            }
            window.clearInterval(heartbeatHandle);
            window.clearInterval(incomingPollHandle);
            contentObserver.disconnect();
            if (bodyObserver) {
                bodyObserver.disconnect();
            }
        };

        var sinceid = 0;
        var pollIncoming = function() {
            Transport.pullEvents(sessionid, sinceid).then(function(events) {
                events.forEach(function(event) {
                    if (event.id > sinceid) {
                        sinceid = event.id;
                    }
                    if (event.eventtype === 'resync_request') {
                        sendPageSnapshot();
                    }
                });
                return null;
            }).catch(function(error) {
                if (error && error.errorcode === 'errorsessionnotactive') {
                    cleanup();
                }
            });
        };

        Str.get_strings([
            {key: 'statusbar_active', component: 'local_remotesupport', param: teachername},
            {key: 'button_finish', component: 'local_remotesupport'}
        ]).then(function(strings) {
            statusbar = document.createElement('div');
            statusbar.className = 'local-remotesupport-statusbar';
            statusbar.setAttribute('role', 'status');

            var text = document.createElement('span');
            text.textContent = strings[0];
            statusbar.appendChild(text);

            var finishurl = M.cfg.wwwroot + '/local/remotesupport/session.php?id=' + encodeURIComponent(sessionid) +
                '&action=finish&sesskey=' + encodeURIComponent(M.cfg.sesskey);
            var finish = document.createElement('a');
            finish.className = 'btn btn-danger btn-sm';
            finish.href = finishurl;
            finish.textContent = strings[1];
            statusbar.appendChild(finish);

            document.body.appendChild(statusbar);
            return null;
        }).catch(function() {
            // Non-fatal: the bar is a convenience, capture still proceeds without it.
        });

        sendPageSnapshot();
        var heartbeatHandle = window.setInterval(sendPageSnapshot, PAGE_HEARTBEAT_MS);

        contentObserver.observe(findCaptureRoot(mode), {childList: true, subtree: true, attributes: false});
        if (bodyObserver) {
            bodyObserver.observe(document.body, {childList: true, subtree: false});
        }

        window.addEventListener('scroll', throttle(sendScroll, SCROLL_THROTTLE_MS), {passive: true});

        var incomingPollHandle = window.setInterval(pollIncoming, INCOMING_POLL_INTERVAL_MS);
    };

    return {
        init: init
    };
});
