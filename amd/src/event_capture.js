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
 * status bar with a finish button and a floating text chat
 * (local_remotesupport/chat_widget). View-only otherwise: the teacher can
 * watch the student's navigation and scroll position but cannot act on the
 * student's page in any way. Loaded on every Moodle page while the student
 * has an active session (see lib.php::local_remotesupport_before_footer()).
 *
 * Only structure is ever captured, never form field values: input/textarea
 * values are stripped client-side before sending, and stripped again
 * authoritatively on the server regardless of what this code does.
 *
 * Also sends the student's mouse position ('cursor' events, x/y in viewport
 * pixels) so the teacher's reconstruction can show where the student is
 * pointing, live and in later playback — a deliberate exception to the base
 * spec's "don't permanently store every mouse movement" guidance (see
 * docs/decisions.md). Bound to the 'mousemove' event itself, not a timer:
 * an idle mouse fires no events at all, so nothing is sent or stored while
 * the student isn't moving it, regardless of the configured sample rate.
 * That rate (local_remotesupport/cursorsamplems, passed in as
 * cursorsamplems) only throttles how often a *moving* mouse is sampled.
 *
 * Also sends 'student_click' events (same x/y shape as 'cursor') on every
 * click that isn't on the plugin's own injected UI, so the teacher's
 * reconstruction can show a brief visual mark (and, optionally, play a
 * sound) at the moment and place the student actually clicked. Unlike
 * 'cursor', not throttled client-side — a click is already a discrete,
 * naturally infrequent event, nothing to sample down.
 *
 * Every 'page' snapshot also separates out any `position: fixed`
 * descendant of the captured root (typically the theme's navbar, only
 * relevant in 'fullpage' mode) into its own `fixed` field — necessary for
 * both cursor/click position accuracy and general reconstruction fidelity
 * once the page is scrolled; see markFixedElements()'s doc comment and
 * screen_renderer.js for why.
 *
 * Three further accuracy improvements, all aimed at the reconstruction
 * matching the real page closely enough that a click reported at a given
 * point actually corresponds to the same clickable element in both —
 * "close enough", not pixel-perfect, since the two are rendered by
 * independent browser engines; see docs/decisions.md for the full
 * reasoning and its limits: (1) inline `<style>` blocks are now captured
 * too (collectInlineStyleText()), not just `<link>`-loaded sheets, so
 * layout rules that only exist inline are no longer silently missing from
 * the reconstruction; (2) the viewport size used to lay out the
 * reconstruction is `document.documentElement.clientWidth/clientHeight`,
 * not `window.innerWidth/innerHeight` — the former excludes the
 * scrollbar's own width, matching the iframe's own scrollbar-less
 * rendering; (3) a page snapshot is now sent immediately after every
 * click (bypassing the usual debounce), and the idle heartbeat runs twice
 * as often, both narrowing the window in which the reconstruction can be
 * stale relative to the student's real, live page.
 *
 * The status bar is `position: fixed` at the bottom of the viewport, same
 * as Moodle core's own sticky footer (theme_boost, `.stickyfooter` /
 * `body.hasstickyfooter`, used for e.g. the Save/Cancel row on long forms
 * like the profile edit page) — without help the two just stack, and ours
 * wins on z-index, hiding the page's own action buttons. Body padding does
 * NOT fix this: the sticky footer is itself `position: fixed`, so it is out
 * of document flow and padding on `<body>` never moves it. Instead this bar
 * watches `body`'s `class` attribute (the sticky footer toggles
 * `hasstickyfooter` on show/hide — including automatically on scroll on
 * narrow viewports, not just once at load) and offsets its own `bottom` by
 * the sticky footer's real height whenever it is visible.
 *
 * @module     local_remotesupport/event_capture
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    ['jquery', 'core/str', 'local_remotesupport/transport', 'local_remotesupport/chat_widget'],
    function($, Str, Transport, ChatWidget) {

    var MAIN_CONTENT_SELECTORS = ['#region-main', 'main[role="main"]', 'main', 'body'];
    var MODAL_SELECTOR = '.modal.show, .modal.in, [aria-modal="true"]';
    var BLOCKED_TAGS = ['script', 'iframe', 'object', 'embed', 'applet', 'noscript', 'link', 'meta'];
    var OWN_CLASS_PREFIX = 'local-remotesupport-';
    var PAGE_HEARTBEAT_MS = 5000;
    var PAGE_DEBOUNCE_MS = 1500;
    var SCROLL_THROTTLE_MS = 300;
    var MAX_HTML_LENGTH = 150000;
    var MAX_FULLPAGE_HTML_LENGTH = 400000;
    var MAX_MODAL_HTML_LENGTH = 30000;
    var MAX_FIXED_HTML_LENGTH = 50000;
    var MAX_INLINE_CSS_LENGTH = 20000;
    var FIXED_MARKER_ATTR = 'data-lrs-fixed-marker';
    var INCOMING_POLL_INTERVAL_MS = 500;

    /**
     * Module-level (not per-session) since it only depends on the fixed
     * OWN_CLASS_PREFIX constant: used both by the mutation observers below
     * (ignore the plugin's own DOM changes) and by markFixedElements()
     * (never treat the plugin's own status bar/chat widget — themselves
     * `position: fixed` — as page content to extract).
     *
     * @param {Node} node
     * @return {Boolean}
     */
    var isOwnElement = function(node) {
        return node.nodeType === 1 && !!(node.closest && node.closest('[class*="' + OWN_CLASS_PREFIX + '"]'));
    };

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
     * Temporarily tags every `position: fixed` descendant of $root (the
     * plugin's own UI excluded) so the same elements can be found again in
     * a detached clone of $root — cloneNode() copies attributes, but a
     * detached node has no computed style of its own to re-check `position`
     * against, so this has to happen on the live, attached element. Must be
     * paired with unmarkFixedElements() right after cloning, to avoid
     * leaving the temporary attribute on the real page.
     *
     * Deliberately `fixed` only, not `sticky`: a `fixed` element's
     * position is viewport-relative regardless of where it sits in the
     * DOM, so relocating it in extractFixedHtml() below is safe. A
     * `sticky` element's position is usually relative to its original
     * normal-flow context (offset, width) — relocating it could look
     * *more* broken than leaving it, so it is left as a known limitation
     * instead (see docs/limitations.md).
     *
     * @param {Element} root
     * @return {Element[]} The same elements that got tagged, for unmarking.
     */
    var markFixedElements = function(root) {
        var marked = [];
        root.querySelectorAll('*').forEach(function(el) {
            if (isOwnElement(el)) {
                return;
            }
            if (window.getComputedStyle(el).position === 'fixed') {
                el.setAttribute(FIXED_MARKER_ATTR, '1');
                marked.push(el);
            }
        });
        return marked;
    };

    /**
     * @param {Element[]} marked
     */
    var unmarkFixedElements = function(marked) {
        marked.forEach(function(el) {
            el.removeAttribute(FIXED_MARKER_ATTR);
        });
    };

    /**
     * Pulls every marked element out of $clone (already cleaned by
     * cleanClone()) and returns their combined HTML, so the caller can
     * render them as siblings of the scroll-simulation wrapper instead of
     * descendants of it — see screen_renderer.js's module doc comment for
     * why a `position: fixed` element nested inside a `transform`-ed
     * ancestor stops behaving as fixed. Skips elements whose closest
     * marked ancestor is also marked: that ancestor's own extraction
     * already carries them along in its outerHTML, so pulling them out a
     * second time would just duplicate them.
     *
     * @param {Element} clone
     * @return {String}
     */
    var extractFixedHtml = function(clone) {
        var candidates = Array.prototype.slice.call(clone.querySelectorAll('[' + FIXED_MARKER_ATTR + ']'));
        var outermost = candidates.filter(function(el) {
            return !el.parentElement || !el.parentElement.closest('[' + FIXED_MARKER_ATTR + ']');
        });

        var html = '';
        outermost.forEach(function(el) {
            el.removeAttribute(FIXED_MARKER_ATTR);
            html += el.outerHTML;
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        });
        return html;
    };

    /**
     * @param {Element} source
     * @param {Number} maxlength
     * @return {{html: String, fixed: String}}
     */
    var buildSanitizedHtml = function(source, maxlength) {
        var markedLive = markFixedElements(source);
        var clone = source.cloneNode(true);
        unmarkFixedElements(markedLive);

        cleanClone(clone);
        var fixed = extractFixedHtml(clone);
        if (fixed.length > MAX_FIXED_HTML_LENGTH) {
            fixed = fixed.substring(0, MAX_FIXED_HTML_LENGTH) + '<!-- truncated -->';
        }

        var html = clone.innerHTML || '';
        if (html.length > maxlength) {
            html = html.substring(0, maxlength) + '<!-- truncated -->';
        }
        return {html: html, fixed: fixed};
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
     * Text of every inline `<style>` block currently in the page —
     * `document.styleSheets` entries with no `href` — so layout rules that
     * only exist inline (course-format CSS, a teacher's "additional HTML",
     * some embedded content) also apply in the reconstruction instead of
     * silently being missing, which was a real source of layout drift
     * between the two. `<link>`-loaded sheets are already covered by
     * collectSameOriginStylesheets() above and are skipped here.
     *
     * @return {String}
     */
    var collectInlineStyleText = function() {
        var css = Array.prototype.slice.call(document.styleSheets)
            .filter(function(sheet) {
                return !sheet.href;
            })
            .map(function(sheet) {
                try {
                    return Array.prototype.slice.call(sheet.cssRules || [])
                        .map(function(rule) {
                            return rule.cssText;
                        })
                        .join('\n');
                } catch (e) {
                    // Some inline stylesheets (e.g. injected by a browser
                    // extension) can throw on .cssRules access; skip them.
                    return '';
                }
            })
            .join('\n');

        if (css.length > MAX_INLINE_CSS_LENGTH) {
            css = css.substring(0, MAX_INLINE_CSS_LENGTH);
        }
        return css;
    };

    /**
     * @param {String} mode 'main' or 'fullpage'
     * @return {Object}
     */
    var buildPageSnapshot = function(mode) {
        var isFullPage = mode === 'fullpage';
        var captured = buildSanitizedHtml(findCaptureRoot(mode), isFullPage ? MAX_FULLPAGE_HTML_LENGTH : MAX_HTML_LENGTH);
        return {
            url: location.pathname + location.search,
            title: document.title,
            html: captured.html,
            // Elements that were `position: fixed` in the live page (most
            // commonly the theme's navbar in 'fullpage' mode) — rendered by
            // screen_renderer.js outside the scroll-simulation wrapper, so
            // they keep behaving as fixed instead of scrolling away with
            // the rest of the content. See markFixedElements() above.
            fixed: captured.fixed,
            // In 'fullpage' mode the modal, like the rest of <body>, is
            // already part of the captured html above; capturing it a
            // second time here would just duplicate it in the payload.
            modal: isFullPage ? null : captureOpenModal(),
            css: collectSameOriginStylesheets(),
            inlineCss: collectInlineStyleText(),
            // documentElement.clientWidth/Height (not window.innerWidth/
            // innerHeight) deliberately: innerWidth/innerHeight include the
            // scrollbar's own width, but the iframe never has a native
            // scrollbar of its own (overflow:hidden, see screen_renderer.js)
            // — sizing it to the full innerWidth would lay out the captured
            // content a scrollbar's-width wider than the student actually
            // sees it, enough to shift where responsive content wraps.
            viewport: {width: document.documentElement.clientWidth, height: document.documentElement.clientHeight},
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
     * @param {Number} ownuserid Current user's id, passed to the chat widget.
     * @param {Number} cursorsamplems Minimum ms between sent cursor positions
     *                                while the mouse is moving (local_remotesupport/cursorsamplems).
     */
    var init = function(sessionid, teachername, capturemode, ownuserid, cursorsamplems) {
        var mode = capturemode === 'fullpage' ? 'fullpage' : 'main';
        var statusbar = null;
        var chat = ChatWidget.init(sessionid, ownuserid, teachername);
        var firstPollDone = false;

        // Moodle core's sticky footer (theme_boost/sticky-footer.js) marks
        // itself visible by adding 'hasstickyfooter' to <body>, and removes
        // it again when hidden (e.g. scrolling down on a narrow viewport) —
        // there is no other reliable moment to hook, since it can toggle
        // repeatedly during a page's lifetime, not just once at load.
        var STICKY_FOOTER_CLASS = 'hasstickyfooter';
        var STICKY_FOOTER_SELECTOR = '.stickyfooter';

        var repositionStatusBar = function() {
            if (!statusbar) {
                return;
            }
            var footerVisible = document.body.classList.contains(STICKY_FOOTER_CLASS);
            var footer = footerVisible ? document.querySelector(STICKY_FOOTER_SELECTOR) : null;
            statusbar.style.bottom = (footer ? footer.offsetHeight : 0) + 'px';
        };

        var stickyFooterObserver = new MutationObserver(repositionStatusBar);
        stickyFooterObserver.observe(document.body, {attributes: true, attributeFilter: ['class']});

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
        // Viewport-relative (clientX/clientY), not document-relative: the
        // teacher's reconstruction places the dot directly over its iframe,
        // which is already sized/scaled to the student's real viewport (see
        // screen_renderer.js) — viewport coordinates map onto it directly,
        // with no separate scroll-position math needed. Position is tracked
        // on every raw 'mousemove' (cheap) but only sent through the
        // throttled wrapper below, since throttle()'s callback takes no
        // arguments of its own.
        var lastCursorX = 0;
        var lastCursorY = 0;
        var sendCursor = function() {
            Transport.pushEvent(sessionid, 'cursor', {x: lastCursorX, y: lastCursorY}).catch(function() {
                // Ignored, see above.
            });
        };
        // Same viewport-relative coordinates as 'cursor'. Every real click
        // is sent (no throttling here, unlike 'cursor' — a click is already
        // an inherently infrequent, discrete event, not a continuous stream
        // to sample); the server's rate_limiter still applies a small floor
        // as a defense against a modified client.
        var sendClick = function(x, y) {
            Transport.pushEvent(sessionid, 'student_click', {x: x, y: y}).catch(function() {
                // Ignored, see above.
            });
        };
        var debouncedSnapshot = debounce(sendPageSnapshot, PAGE_DEBOUNCE_MS);

        // The status bar lives directly under <body> too; the observers
        // below must not mistake its own insertions/removals for
        // alumno-driven changes worth re-syncing over — that would just be a
        // spurious resend, not an infinite loop, but it is exactly the
        // local/remote conflation the spec asks to avoid. isOwnElement()
        // (module-level, see above) is checked via closest(), not just the
        // node's own class list, so it still catches e.g. a button added
        // deep inside the status bar while watching the whole <body> in
        // 'fullpage' mode — a case the original direct-children-only check
        // never had to handle, since in 'main' mode the status bar never
        // lives inside the observed main-content root at all.
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
            stickyFooterObserver.disconnect();
            chat.destroy();
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
                var isReplay = !firstPollDone;
                firstPollDone = true;
                events.forEach(function(event) {
                    if (event.id > sinceid) {
                        sinceid = event.id;
                    }
                    if (event.eventtype === 'resync_request') {
                        sendPageSnapshot();
                    } else if (event.eventtype === 'chat_message') {
                        chat.receive(event, isReplay);
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
            repositionStatusBar();
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

        var throttledSendCursor = throttle(sendCursor, cursorsamplems > 0 ? cursorsamplems : 500);
        window.addEventListener('mousemove', function(e) {
            lastCursorX = e.clientX;
            lastCursorY = e.clientY;
            throttledSendCursor();
        }, {passive: true});

        // Bubbling phase 'click' on document catches every click on the
        // page, including on links/buttons before their default action
        // runs. Clicks on the plugin's own injected UI (status bar, chat
        // widget) are never part of the alumno's captured page, so showing
        // a mark for them on the teacher's side would be meaningless noise.
        document.addEventListener('click', function(e) {
            if (!isOwnElement(e.target)) {
                sendClick(e.clientX, e.clientY);
                // Also resyncs the reconstruction right away, bypassing
                // debouncedSnapshot()'s 1.5s delay: a click is exactly the
                // moment Moodle is most likely to change the DOM right
                // after (open a menu, disable a button, navigate), and a
                // real click is inherently rate-limited by human input
                // speed, so sending an extra, non-debounced snapshot here
                // does not risk the flood a rapid burst of mutations could
                // cause. Narrows the window in which the reconstruction
                // the teacher sees can be stale relative to what the
                // student is actually looking at.
                sendPageSnapshot();
            }
        }, {passive: true});

        var incomingPollHandle = window.setInterval(pollIncoming, INCOMING_POLL_INTERVAL_MS);
    };

    return {
        init: init
    };
});
