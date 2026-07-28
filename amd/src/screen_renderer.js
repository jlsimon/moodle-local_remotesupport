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
 * to fully lock down).
 *
 * The iframe uses sandbox="allow-same-origin" with no allow-scripts token:
 * this is what lets this module reach into contentDocument (an opaque/
 * null-origin frame would reject that cross-origin access), while still
 * fully disabling script execution inside the frame regardless of origin.
 * The server-side sanitizer (html_sanitizer.php) is still the authoritative
 * content cleaner; this is a second, independent layer.
 *
 * @module     local_remotesupport/screen_renderer
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var VIEWPORT_CONTENT_ID = 'local-remotesupport-viewport-content';

    /**
     * @param {HTMLIFrameElement} iframe
     * @param {HTMLElement} viewportWrapper
     * @return {Object} {applyViewportSize, applyScrollPosition, renderPage}
     */
    var create = function(iframe, viewportWrapper) {
        var lastViewport = null;

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
            // html/body get no scrollable overflow of their own — see the
            // module doc comment. The modal stays outside the translated
            // wrapper so it keeps behaving like a normal position:fixed
            // overlay instead of moving with the scroll simulation (a CSS
            // transform on an ancestor would otherwise turn it into the
            // containing block for fixed-position descendants).
            iframe.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8">' +
                '<base href="' + M.cfg.wwwroot + '/">' + links +
                '<style>html,body{margin:0;overflow:hidden;height:100%;}</style>' +
                '</head><body>' +
                '<div id="' + VIEWPORT_CONTENT_ID + '">' + payload.html + '</div>' +
                modalHtml + '</body></html>';
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
            reapplyViewportSize: reapplyViewportSize
        };
    };

    return {
        create: create
    };
});
