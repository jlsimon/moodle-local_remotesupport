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
 * Shared "what counts as clickable, and how do we point back at it" logic,
 * used on both ends of a highlight: event_capture.js (student side, marking
 * what the alumno is hovering/typing in) and screen_renderer.js (teacher
 * side, picking an element inside the reconstructed iframe to point at on
 * the alumno's real page — see docs/architecture.md). Kept in one module so
 * the two directions can never quietly drift into using different notions
 * of "clickable" or different selector strategies.
 *
 * @module     local_remotesupport/dom_selector
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var CLICKABLE_SELECTOR = 'a[href], button, input[type="submit"], input[type="button"], ' +
        'input[type="checkbox"], input[type="radio"], select, summary, label, ' +
        '[role="button"], [role="link"], [role="tab"], [role="menuitem"]';

    // Text-like fields, same list event_capture.js already uses to decide
    // which focused field is worth showing the teacher as "typing"
    // (password and hidden excluded — never even a candidate, consistent
    // with how the rest of the plugin treats them as a stricter category).
    // Not part of CLICKABLE_SELECTOR itself: a text field isn't something
    // the alumno's mouse "clicks" in the same sense a link or button is, so
    // it stays out of the alumno-side hover highlight (which already has
    // its own, focus-based mechanism for remarking a typed-in field) — see
    // POINTABLE_SELECTOR below for where it *is* included.
    var TEXT_FIELD_SELECTOR = 'textarea, input:not([type]), input[type="text"], input[type="search"], ' +
        'input[type="url"], input[type="tel"], input[type="number"], input[type="email"]';

    // What the teacher may point at (screen_renderer.js's picking mode) —
    // CLICKABLE_SELECTOR plus text fields. Deliberately a separate list
    // from CLICKABLE_SELECTOR rather than folding text fields into it:
    // pointing is a different, non-destructive act from the alumno's own
    // mouse-hover signal (which stays clickable-only, see above), and
    // unlike Fase 5/6's remote click/write, there is no field-safety policy
    // to apply here — pointing never executes anything or reveals a value,
    // so nothing needs excluding on security grounds (not even password
    // fields, though TEXT_FIELD_SELECTOR excludes those too, for
    // consistency with how the rest of the plugin treats them).
    var POINTABLE_SELECTOR = CLICKABLE_SELECTOR + ', ' + TEXT_FIELD_SELECTOR;

    // Note: screen_renderer.js injects captured content as the innerHTML of a
    // wrapper div with this id (see its own VIEWPORT_CONTENT_ID) — a
    // synthetic node that exists only inside the teacher's reconstructed
    // iframe, never on the alumno's real page. buildRobustSelector() must
    // never anchor a selector on it: doing so would produce something like
    // '#local-remotesupport-viewport-content > div:nth-of-type(3)', which
    // can never match anything real (see docs/decisions.md, the
    // "señalar un elemento clicable" bugfix entry). Shared here, not
    // duplicated in screen_renderer.js, so the two can never drift apart.
    var VIEWPORT_CONTENT_ID = 'local-remotesupport-viewport-content';

    // A safety net against a pathological/circular DOM, not a real limit:
    // real Moodle markup can easily nest 10-15+ levels deep (Bootstrap
    // wrappers, section/activity containers) before reaching either an
    // ancestor with an id or the document root, and buildRobustSelector()
    // needs to actually reach one of those two to produce a selector
    // querySelector() can resolve reliably — stopping short at an
    // arbitrary depth produced a selector anchored to nothing, which
    // essentially never matched anything for elements without an id (the
    // common case: most Moodle links/buttons don't have one). See
    // docs/decisions.md.
    var HOVER_SELECTOR_MAX_DEPTH = 30;

    /**
     * Nearest interactive ancestor of $node (including itself), or null.
     *
     * @param {Node} node
     * @return {Element|null}
     */
    var findClickableAncestor = function(node) {
        if (!node || node.nodeType !== 1 || !node.closest) {
            return null;
        }
        return node.closest(CLICKABLE_SELECTOR);
    };

    /**
     * Same as findClickableAncestor(), but also matches text fields — used
     * only for the teacher's picking mode (see POINTABLE_SELECTOR above).
     *
     * @param {Node} node
     * @return {Element|null}
     */
    var findPointableAncestor = function(node) {
        if (!node || node.nodeType !== 1 || !node.closest) {
            return null;
        }
        return node.closest(POINTABLE_SELECTOR);
    };

    /**
     * Builds a selector that (best-effort) identifies the same element
     * again in a different copy of the same page's DOM — a captured
     * snapshot, another moment in time, or the alumno's real live page,
     * depending on the caller. Preference order mirrors the "selectores
     * robustos" guidance from the original spec: a stable `id` first
     * (survives reordering/insertions elsewhere on the page, so the most
     * reliable choice by far), falling back to a structural path (tag +
     * position among same-tag siblings) that walks up to either an
     * ancestor with an `id` or the document root, whichever comes first —
     * HOVER_SELECTOR_MAX_DEPTH only guards against never terminating, it
     * is not meant to be hit in practice. The structural fallback is
     * genuinely best-effort: if the two copies of the page have drifted
     * even slightly, sibling order could have shifted and the selector
     * might miss or match the wrong element — accepted, see
     * docs/limitations.md, since a miss just means no highlight shows, not
     * a wrong action.
     *
     * When called on an element inside screen_renderer.js's reconstructed
     * iframe (the teacher picking an element to point at), the climb stops
     * — without emitting an anchor — the moment it reaches the synthetic
     * VIEWPORT_CONTENT_ID wrapper, instead of treating that node's id as a
     * real one: the resulting selector is then relative to that wrapper's
     * children, meant to be resolved with `captureRoot.querySelector()` on
     * the alumno's real page (captureRoot being the same root that was
     * captured into that wrapper in the first place — see
     * event_capture.js's applyTeacherPointer()), not `document.querySelector()`
     * directly. Never reached when called from the alumno's own real page
     * (the hover/typing case): that id does not exist there at all.
     *
     * @param {Element} el
     * @return {String|null}
     */
    var buildRobustSelector = function(el) {
        if (el.id && el.id !== VIEWPORT_CONTENT_ID) {
            return '#' + CSS.escape(el.id);
        }

        var parts = [];
        var node = el;
        var depth = 0;
        while (node && node.nodeType === 1 && depth < HOVER_SELECTOR_MAX_DEPTH) {
            if (node.id) {
                if (node.id !== VIEWPORT_CONTENT_ID) {
                    parts.unshift('#' + CSS.escape(node.id));
                }
                break;
            }
            var parent = node.parentElement;
            if (!parent) {
                parts.unshift(node.tagName.toLowerCase());
                break;
            }
            // Plain for loop, not Array.prototype.filter() with an inline
            // callback: a function declared inside a while loop referencing
            // a variable declared in that same loop trips eslint's
            // no-loop-func rule, even though it is always invoked
            // synchronously within the same iteration here.
            var position = 0;
            for (var i = 0; i < parent.children.length; i++) {
                if (parent.children[i].tagName !== node.tagName) {
                    continue;
                }
                position++;
                if (parent.children[i] === node) {
                    break;
                }
            }
            parts.unshift(node.tagName.toLowerCase() + ':nth-of-type(' + position + ')');
            node = parent;
            depth++;
        }
        return parts.join(' > ');
    };

    return {
        CLICKABLE_SELECTOR: CLICKABLE_SELECTOR,
        TEXT_FIELD_SELECTOR: TEXT_FIELD_SELECTOR,
        POINTABLE_SELECTOR: POINTABLE_SELECTOR,
        VIEWPORT_CONTENT_ID: VIEWPORT_CONTENT_ID,
        findClickableAncestor: findClickableAncestor,
        findPointableAncestor: findPointableAncestor,
        buildRobustSelector: buildRobustSelector
    };
});
