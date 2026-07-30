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
 * Renders a captured 'page'/'scroll' event into a sandboxed iframe, scaled
 * to fit its wrapper. Shared by event_player.js (live viewing, polling for
 * new events) and session_replay.js (stepping through a stored recording) —
 * both need the exact same sandboxed reconstruction, just fed differently.
 *
 * The captured content's own document has no scrollable overflow at all
 * (html/body forced to `overflow: hidden`) — its "scroll position" is
 * simulated by CSS-translating an inner wrapper div, driven only by synced
 * 'page'/'scroll' events. See event_player.js's original module doc comment
 * for the full history of why (native iframe scrolling proved unreliable
 * to fully lock down). A `transform` on that wrapper turns it into the
 * containing block for any `position: fixed` descendant, breaking its fixed
 * behavior — event_capture.js works around this by extracting fixed
 * elements (the theme's navbar, typically) into their own `payload.fixed`,
 * rendered here as a sibling of the wrapper instead of inside it. This
 * matters for more than visual fidelity: the cursor/click marks below are
 * positioned by scaling raw viewport coordinates, which is only accurate
 * once the reconstructed layout actually matches the real page's — a fixed
 * navbar scrolling away when it shouldn't shifts everything below it out of
 * alignment as soon as the student scrolls. See docs/decisions.md.
 *
 * Also renders `payload.inlineCss` (captured `<style>` blocks that had no
 * `href` — course-format CSS, a teacher's "additional HTML", etc. — see
 * event_capture.js's collectInlineStyleText()) as an extra `<style>` tag,
 * so layout rules that only ever existed inline are not silently missing
 * from the reconstruction. It is raw CSS text, not HTML that already went
 * through the server's sanitizer, so a literal `</style` in it is escaped
 * before concatenating it into the srcdoc string — otherwise it would
 * close the tag early and let arbitrary markup from the payload leak into
 * the page instead of staying inert CSS.
 *
 * The iframe uses sandbox="allow-same-origin" with no allow-scripts token:
 * this is what lets this module reach into contentDocument (an opaque/
 * null-origin frame would reject that cross-origin access), while still
 * fully disabling script execution inside the frame regardless of origin.
 * The server-side sanitizer (html_sanitizer.php) is still the authoritative
 * content cleaner; this is a second, independent layer.
 *
 * Also draws a small dot marking the student's own mouse position
 * ('cursor' events) and a brief fading "ripple" mark at each click
 * ('student_click' events), both positioned as siblings of the iframe
 * rather than something injected into its (sandboxed, cross-document)
 * content — both reuse the same scale factor applyViewportSize() already
 * computes, so they line up with the iframe's visual (post-`transform:
 * scale()`) size with no separate math. The click mark can also play a
 * short synthesized "tick" sound (Web Audio API, no audio asset shipped)
 * — callers decide whether to invoke playClickSound() at all, so it stays
 * a pure function with no notion of a mute setting of its own. The cursor
 * dot only hides on a real navigation (the 'page' event's URL actually
 * changing), not on every re-sent snapshot of the same page — otherwise a
 * student who simply stops moving the mouse for a moment would see their
 * own cursor vanish from the teacher's view each time the next periodic
 * snapshot arrived.
 *
 * applyCursorPosition() also accepts an optional selector (a 'cursor'
 * event's `hover` field, see event_capture.js) identifying the clickable
 * element, if any, the student's mouse is over — applyHoverHighlight()
 * outlines that same element inside the reconstructed document, found by
 * re-querying it there rather than trusting the dot's own coordinates.
 * This stays exact even when the reconstruction's layout does not match
 * the real page's pixel-for-pixel, which the dot's position alone cannot.
 * Once an element is found this way, the dot itself is re-centered on it
 * too, overriding its raw coordinate position: matching by identity is
 * strictly more trustworthy than the dot's own (only ever approximate)
 * x/y math, so whenever both disagree, the element wins.
 * Unlike the cursor dot (a sibling of the iframe, unaffected by it), the
 * highlight lives *inside* the iframe's own document, so renderPage()
 * rebuilding iframe.srcdoc — which happens on every 'page' event, not
 * just real navigations — would otherwise wipe it out even while the
 * student's mouse stays right where it was; renderPage() re-applies
 * lastHoverSelector once the new document loads to compensate.
 *
 * @module     local_remotesupport/screen_renderer
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var VIEWPORT_CONTENT_ID = 'local-remotesupport-viewport-content';
    var CLICK_MARK_DURATION_MS = 600;

    var audioCtx = null;

    /**
     * A short synthesized "tick", not an audio file — keeps the plugin
     * free of any bundled/licensed asset. Errors (no Web Audio support, or
     * blocked by the browser's autoplay policy before any user gesture on
     * this page) are swallowed: sound is a non-essential convenience, never
     * something the rest of the feature depends on.
     */
    var playClickSound = function() {
        try {
            var AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) {
                return;
            }
            if (!audioCtx) {
                audioCtx = new AudioContextClass();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            var oscillator = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            oscillator.type = 'square';
            oscillator.frequency.value = 1000;
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.05);
            oscillator.connect(gain);
            gain.connect(audioCtx.destination);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.05);
        } catch (e) {
            // Ignored, see above.
        }
    };

    /**
     * @param {HTMLIFrameElement} iframe
     * @param {HTMLElement} viewportWrapper
     * @return {Object} {applyViewportSize, applyScrollPosition, renderPage,
     *                    applyCursorPosition, hideCursor, showClickMark, playClickSound}
     */
    var create = function(iframe, viewportWrapper) {
        var lastViewport = null;
        var lastScale = 1;
        var lastCursor = null;
        var cursorEl = null;
        var lastUrl = null;
        var lastHighlightedEl = null;
        var lastHoverSelector = null;
        var lastTypingEl = null;
        var lastTypingSelector = null;

        // Forces the iframe to actually be the alumno's own reported
        // viewport size (not just visually similar), then shrinks it back
        // down with a CSS transform to fit the available space. On a
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
            lastScale = scale;

            iframe.style.width = width + 'px';
            iframe.style.height = height + 'px';
            iframe.style.transform = 'scale(' + scale + ')';
            viewportWrapper.style.height = (height * scale) + 'px';

            if (lastCursor) {
                positionCursorEl(lastCursor.x, lastCursor.y);
            }
        };

        /**
         * @param {Number} x Viewport-relative pixels, as sent by event_capture.js.
         * @param {Number} y Viewport-relative pixels, as sent by event_capture.js.
         */
        var positionCursorEl = function(x, y) {
            if (!cursorEl) {
                cursorEl = document.createElement('div');
                cursorEl.className = 'local-remotesupport-student-cursor';
                viewportWrapper.appendChild(cursorEl);
            }
            cursorEl.style.left = (x * lastScale) + 'px';
            cursorEl.style.top = (y * lastScale) + 'px';
            cursorEl.style.display = '';
        };

        /**
         * Highlights the element $selector resolves to inside the
         * reconstructed document, if any — a second, coordinate-independent
         * way of showing what the student is pointing at (see
         * event_capture.js's buildRobustSelector()), unaffected by any
         * mismatch between the dot's position and the real page's layout.
         * Clears any previously highlighted element first, regardless of
         * whether $selector matches anything now, so hovering away from a
         * clickable element (selector goes null) removes the old highlight.
         *
         * Remembers $selector (lastHoverSelector) regardless of whether it
         * actually matched anything, so renderPage() can re-apply it after
         * rebuilding the iframe's document for a same-page snapshot
         * refresh — see renderPage()'s doc comment for why that is
         * necessary (rebuilding the srcdoc discards the highlight class
         * along with the rest of the old document, even though nothing
         * about the actual hover state changed).
         *
         * Also re-centers the cursor dot on the highlighted element once
         * found, overriding whatever the raw x/y coordinate placed it at.
         * The dot's own coordinate math is only ever approximate (see
         * docs/decisions.md, "precisión 'lo bastante cerca'"), but once an
         * element is identified by matching, not by position, we know for
         * certain the real mouse is somewhere on it — its center is a
         * strictly better estimate than an imprecise raw coordinate that
         * might not even land inside it. Only affects positioning while a
         * highlight is actually showing; with none, the dot keeps the
         * coordinate-based position applyCursorPosition() already gave it.
         *
         * @param {String|null|undefined} selector
         */
        var applyHoverHighlight = function(selector) {
            lastHoverSelector = selector || null;
            if (lastHighlightedEl) {
                lastHighlightedEl.classList.remove('local-remotesupport-hover-highlight');
                lastHighlightedEl = null;
            }
            if (!selector) {
                return;
            }
            var doc = iframe.contentDocument;
            if (!doc) {
                return;
            }
            var el;
            try {
                el = doc.querySelector(selector);
            } catch (e) {
                // Malformed selector (e.g. the structural fallback missed
                // due to a stale snapshot) — no highlight, not an error.
                return;
            }
            if (el) {
                el.classList.add('local-remotesupport-hover-highlight');
                lastHighlightedEl = el;
                // getBoundingClientRect() here is relative to the iframe's
                // own (unscaled, full-size) internal viewport — the same
                // convention positionCursorEl() already expects for its
                // x/y, since the outer `transform: scale()` on the iframe
                // element is a purely visual effect its own document has
                // no notion of. No extra conversion needed.
                var rect = el.getBoundingClientRect();
                var centerX = rect.left + (rect.width / 2);
                var centerY = rect.top + (rect.height / 2);
                positionCursorEl(centerX, centerY);
                // Also updates lastCursor (not just the DOM position) so a
                // later resize/fullscreen toggle re-applies the snapped
                // position via applyViewportSize(), not the raw coordinate
                // this overrode.
                lastCursor = {x: centerX, y: centerY};
            }
        };

        /**
         * Remarks the field the alumno is currently typing in, the same way
         * applyHoverHighlight() remarks a hovered clickable element, but
         * with its own CSS class (visually distinct — "pointing at" and
         * "typing in" are different signals for the teacher to read) and
         * without touching the cursor dot's position, since keyboard focus
         * has no meaningful mouse coordinate to re-center on.
         *
         * @param {String|null|undefined} selector
         */
        var applyTypingHighlight = function(selector) {
            lastTypingSelector = selector || null;
            if (lastTypingEl) {
                lastTypingEl.classList.remove('local-remotesupport-typing-highlight');
                lastTypingEl = null;
            }
            if (!selector) {
                return;
            }
            var typingDoc = iframe.contentDocument;
            if (!typingDoc) {
                return;
            }
            var typingField;
            try {
                typingField = typingDoc.querySelector(selector);
            } catch (e) {
                // Malformed/stale selector — no highlight, not an error, see
                // applyHoverHighlight()'s doc comment.
                return;
            }
            if (typingField) {
                typingField.classList.add('local-remotesupport-typing-highlight');
                lastTypingEl = typingField;
            }
        };

        /**
         * @param {Number} x Viewport-relative pixels.
         * @param {Number} y Viewport-relative pixels.
         * @param {String|null} [hoverSelector] See applyHoverHighlight().
         * @param {String|null} [typingSelector] See applyTypingHighlight().
         */
        var applyCursorPosition = function(x, y, hoverSelector, typingSelector) {
            if (typeof x !== 'number' || typeof y !== 'number') {
                return;
            }
            lastCursor = {x: x, y: y};
            positionCursorEl(x, y);
            applyHoverHighlight(hoverSelector);
            applyTypingHighlight(typingSelector);
        };

        var hideCursor = function() {
            lastCursor = null;
            if (cursorEl) {
                cursorEl.style.display = 'none';
            }
            applyHoverHighlight(null);
            applyTypingHighlight(null);
        };

        /**
         * Transient mark, unlike the cursor dot: created fresh each time and
         * removed once its CSS animation finishes, rather than a single
         * element that gets repositioned.
         *
         * @param {Number} x Viewport-relative pixels.
         * @param {Number} y Viewport-relative pixels.
         */
        var showClickMark = function(x, y) {
            if (typeof x !== 'number' || typeof y !== 'number') {
                return;
            }
            var mark = document.createElement('div');
            mark.className = 'local-remotesupport-click-mark';
            mark.style.left = (x * lastScale) + 'px';
            mark.style.top = (y * lastScale) + 'px';
            viewportWrapper.appendChild(mark);
            window.setTimeout(function() {
                if (mark.parentNode) {
                    mark.parentNode.removeChild(mark);
                }
            }, CLICK_MARK_DURATION_MS);
        };

        window.addEventListener('resize', function() {
            if (lastViewport) {
                applyViewportSize(lastViewport);
            }
        });

        // Positions the captured content by CSS-translating the wrapper div
        // instead of using native document scrolling — see the module doc
        // comment above. `x`/`y` are the alumno's real document-coordinate
        // scroll position.
        var applyScrollPosition = function(x, y) {
            var doc = iframe.contentDocument;
            var content = doc && doc.getElementById(VIEWPORT_CONTENT_ID);
            if (content && typeof x === 'number' && typeof y === 'number') {
                content.style.transform = 'translate(' + (-x) + 'px, ' + (-y) + 'px)';
            }
        };

        /**
         * @param {Object} payload Decoded 'page' event payload.
         * @param {HTMLElement} [pageInfo] Optional element to show title/url in.
         */
        var renderPage = function(payload, pageInfo) {
            if (pageInfo) {
                pageInfo.textContent = (payload.title || '') + ' — ' + (payload.url || '');
            }
            applyViewportSize(payload.viewport);
            // A cursor position captured on the page being replaced no
            // longer corresponds to anything meaningful, so a genuine
            // navigation (URL change) hides it and waits for a fresh
            // 'cursor' event on the new page. A 'page' event does not
            // always mean a real navigation, though — the same page is
            // re-sent on every heartbeat (PAGE_HEARTBEAT_MS) and now also
            // right after each click (see event_capture.js) — hiding the
            // dot on every one of those, not just real navigations, meant
            // it disappeared any time the student's mouse stayed still for
            // longer than the interval between two such re-sends, which is
            // not what "stopped moving" should look like.
            if (payload.url !== lastUrl) {
                hideCursor();
            }
            lastUrl = payload.url;
            // Rebuilding iframe.srcdoc below replaces the iframe's whole
            // document, discarding the highlight class applied to whatever
            // element carried it — including on a same-page refresh, where
            // nothing about the actual hover state changed. Re-applying
            // lastHoverSelector once the new document is ready (below) is
            // what iframe.onload is for; if this *is* a real navigation,
            // hideCursor() just above already reset it to null, so this
            // ends up being a harmless no-op in that case.

            var links = (Array.isArray(payload.css) ? payload.css : [])
                .filter(function(href) {
                    return typeof href === 'string' && href.indexOf(M.cfg.wwwroot) === 0;
                })
                .map(function(href) {
                    return '<link rel="stylesheet" href="' + href.replace(/"/g, '&quot;') + '">';
                })
                .join('');

            // Raw CSS text, not HTML that already went through the server's
            // DOM-based sanitizer — embedding it as a plain string inside
            // '<style>...</style>' means a literal '</style' in the payload
            // would otherwise close the tag early and inject arbitrary
            // markup into the srcdoc. Breaking up that exact sequence is
            // enough to defeat it; CSS has no legitimate reason to contain
            // it. Same class of precaution as the '"' escaping just above
            // for stylesheet hrefs.
            var inlineCssTag = typeof payload.inlineCss === 'string' && payload.inlineCss
                ? '<style>' + payload.inlineCss.replace(/<\/style/gi, '<\\/style') + '</style>'
                : '';

            var modalHtml = typeof payload.modal === 'string' ? payload.modal : '';
            // Elements that were `position: fixed` in the live page
            // (typically the theme's navbar — see event_capture.js's
            // markFixedElements()), same reasoning as the modal just below:
            // kept outside the translated wrapper so they keep behaving as
            // fixed relative to the iframe's own viewport instead of
            // scrolling away with the rest of the content.
            var fixedHtml = typeof payload.fixed === 'string' ? payload.fixed : '';

            var pendingScroll = payload.scroll || null;
            iframe.onload = function() {
                if (pendingScroll) {
                    applyScrollPosition(pendingScroll.x, pendingScroll.y);
                }
                applyHoverHighlight(lastHoverSelector);
                applyTypingHighlight(lastTypingSelector);
            };
            // html/body get no scrollable overflow of their own — see the
            // module doc comment. The modal and any extracted fixed
            // elements stay outside the translated wrapper so they keep
            // behaving like normal position:fixed overlays instead of
            // moving with the scroll simulation (a CSS transform on an
            // ancestor would otherwise turn it into the containing block
            // for fixed-position descendants).
            iframe.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8">' +
                '<base href="' + M.cfg.wwwroot + '/">' + links + inlineCssTag +
                '<style>html,body{margin:0;overflow:hidden;height:100%;}' +
                '.local-remotesupport-hover-highlight{' +
                'outline:3px solid rgba(220,53,69,.85) !important;' +
                'outline-offset:2px !important;}' +
                '.local-remotesupport-typing-highlight{' +
                'outline:3px solid rgba(40,167,69,.85) !important;' +
                'outline-offset:2px !important;' +
                'background-color:rgba(40,167,69,.12) !important;}</style>' +
                '</head><body>' +
                '<div id="' + VIEWPORT_CONTENT_ID + '">' + payload.html + '</div>' +
                modalHtml + fixedHtml + '</body></html>';
        };

        // Re-applies the last known viewport size, e.g. after a fullscreen
        // toggle changes how much space is available. A no-op before any
        // 'page' event has ever been rendered.
        var reapplyViewportSize = function() {
            if (lastViewport) {
                applyViewportSize(lastViewport);
            }
        };

        return {
            applyViewportSize: applyViewportSize,
            applyScrollPosition: applyScrollPosition,
            renderPage: renderPage,
            reapplyViewportSize: reapplyViewportSize,
            applyCursorPosition: applyCursorPosition,
            hideCursor: hideCursor,
            showClickMark: showClickMark,
            playClickSound: playClickSound
        };
    };

    return {
        create: create
    };
});
