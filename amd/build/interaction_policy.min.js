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
 * Single centralised policy for whether a remote click or a remote write
 * is allowed on a given element of the student's real page. Nothing else
 * in the plugin should decide this independently — deliberately, per the
 * document: "no dispersar las reglas de seguridad por distintos módulos".
 *
 * Allowlist first, blocklist second: an element must match at least one
 * allowed category *and* match none of the blocked ones. A blocklist
 * alone would default to "anything not explicitly dangerous is fine",
 * which is the wrong posture for "un conjunto pequeño y claramente
 * definido de clics seguros".
 *
 * This can only run client-side: the server never has the resolved DOM
 * element, only an opaque selector string, so it cannot evaluate this
 * policy itself (see docs/limitations.md for the resulting test-coverage
 * gap — this file has no PHPUnit equivalent).
 *
 * @module     local_remotesupport/interaction_policy
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var BLOCKED_KEYWORDS = [
        'eliminar', 'borrar', 'delete', 'remove', 'quitar',
        'enviar', 'submit', 'confirmar entrega', 'finalizar intento', 'finish attempt', 'submit all',
        'comprar', 'buy', 'purchase', 'pay', 'pagar',
        'contraseña', 'password', 'correo electrónico', 'change email',
        'logout', 'salir', 'unenrol', 'desmatricular',
        'cerrar cuenta', 'delete account', 'eliminar cuenta'
    ];

    var BLOCKED_PATH_FRAGMENTS = [
        '/admin/', '/login/', 'delete', 'remove', 'logout', 'unenrol', 'password',
        '/user/edit', '/user/editadvanced', '/user/policy', '/user/delete'
    ];

    // Pages where no field write is allowed at all, regardless of the
    // individual field: quiz attempts/summaries/reviews and assignment
    // submissions are exactly "respuestas de cuestionarios" and "entregas
    // evaluables" from the document's blocklist. Checked against the
    // current page, not the field, because a field-level check alone
    // cannot distinguish "a quiz question's answer box" from "an
    // unrelated text field that happens to look the same".
    var BLOCKED_PAGE_PATH_FRAGMENTS = [
        '/mod/quiz/attempt', '/mod/quiz/summary', '/mod/quiz/review',
        '/mod/assign/view'
    ];

    var BLOCKED_INPUT_TYPES = [
        'password', 'email', 'hidden', 'file', 'tel', 'number'
    ];

    var BLOCKED_AUTOCOMPLETE_VALUES = [
        'current-password', 'new-password', 'one-time-code',
        'cc-number', 'cc-csc', 'cc-exp', 'cc-name', 'email', 'tel'
    ];

    var BLOCKED_FIELD_NAME_KEYWORDS = [
        'password', 'passwd', 'token', 'apikey', 'api_key', 'secret',
        'card', 'cvv', 'ccnum', 'email', 'iban', 'account'
    ];

    /**
     * @param {Element} el
     * @return {String}
     */
    var visibleLabel = function(el) {
        return (el.getAttribute('aria-label') || el.getAttribute('title') || el.textContent || '')
            .trim().substring(0, 120);
    };

    /**
     * @param {String} text
     * @return {Boolean}
     */
    var containsBlockedKeyword = function(text) {
        var lower = text.toLowerCase();
        return BLOCKED_KEYWORDS.some(function(word) {
            return lower.indexOf(word) !== -1;
        });
    };

    /**
     * @param {String} href
     * @return {Boolean}
     */
    var isSameOrigin = function(href) {
        try {
            return new URL(href, window.location.href).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    };

    /**
     * @param {String} href
     * @return {Boolean}
     */
    var isBlockedPath = function(href) {
        var lower = href.toLowerCase();
        return BLOCKED_PATH_FRAGMENTS.some(function(fragment) {
            return lower.indexOf(fragment) !== -1;
        });
    };

    /**
     * Whether the element belongs to at least one recognised safe category.
     *
     * @param {Element} el
     * @return {Boolean}
     */
    var matchesAllowlist = function(el) {
        if (el.hasAttribute('data-remotesupport-safe')) {
            return true;
        }
        var tag = el.tagName.toLowerCase();
        if (tag === 'a' && el.hasAttribute('href')) {
            return true;
        }
        if (tag === 'button' && (!el.closest('form') || el.getAttribute('type') === 'button')) {
            return true;
        }
        if (el.getAttribute('role') === 'tab' || el.closest('[role="tablist"]')) {
            return true;
        }
        if (el.hasAttribute('data-toggle') || el.hasAttribute('data-bs-toggle')) {
            // Bootstrap collapse (accordions) and dropdown (non-destructive menus) triggers.
            var toggle = (el.getAttribute('data-toggle') || el.getAttribute('data-bs-toggle') || '').toLowerCase();
            return toggle === 'collapse' || toggle === 'dropdown' || toggle === 'tab';
        }
        return false;
    };

    /**
     * Whether the element (or something about its action) is explicitly blocked.
     *
     * @param {Element} el
     * @return {Boolean}
     */
    var matchesBlocklist = function(el) {
        var tag = el.tagName.toLowerCase();

        if (el.closest('iframe')) {
            // Structurally unreachable via querySelector on the top document,
            // kept as an explicit, documented guard rather than relying on that.
            return true;
        }
        if (tag === 'input' && (el.getAttribute('type') || '').toLowerCase() === 'file') {
            return true;
        }
        if ((el.getAttribute('type') || '').toLowerCase() === 'submit') {
            return true;
        }
        if (tag === 'button' && el.closest('form') && el.getAttribute('type') !== 'button') {
            // A <button> inside a <form> defaults to type=submit.
            return true;
        }
        if (el.hasAttribute('download')) {
            return true;
        }
        if (tag === 'a') {
            var href = el.getAttribute('href') || '';
            if (href.toLowerCase().indexOf('javascript:') === 0) {
                return true;
            }
            if (!isSameOrigin(href)) {
                return true;
            }
            if (isBlockedPath(href)) {
                return true;
            }
        }
        if (containsBlockedKeyword(visibleLabel(el))) {
            return true;
        }
        return false;
    };

    /**
     * @param {Element} el
     * @return {{allowed: Boolean, reason: String}}
     */
    var canClick = function(el) {
        if (!el || el.nodeType !== 1) {
            return {allowed: false, reason: 'notfound'};
        }
        if (matchesBlocklist(el)) {
            return {allowed: false, reason: 'blocklist'};
        }
        if (!matchesAllowlist(el)) {
            return {allowed: false, reason: 'notallowlisted'};
        }
        return {allowed: true, reason: ''};
    };

    /**
     * Whether the current page is one where no remote write is allowed at
     * all (quiz attempts, assignment submissions), regardless of field.
     *
     * @return {Boolean}
     */
    var isBlockedPageForInput = function() {
        var path = window.location.pathname.toLowerCase();
        return BLOCKED_PAGE_PATH_FRAGMENTS.some(function(fragment) {
            return path.indexOf(fragment) !== -1;
        });
    };

    /**
     * Whether a value may be written into this element. Deliberately far
     * narrower than canClick(): only plain <input> text-like fields and
     * <textarea> are ever allowed — rich editors (Atto/TinyMCE) are
     * contenteditable <div>s, not <input>/<textarea>, so excluding every
     * other tag rules them out without needing to detect them by name.
     *
     * @param {Element} el
     * @return {{allowed: Boolean, reason: String}}
     */
    var canSetValue = function(el) {
        if (!el || el.nodeType !== 1) {
            return {allowed: false, reason: 'notfound'};
        }
        if (isBlockedPageForInput()) {
            return {allowed: false, reason: 'blockedpage'};
        }
        if (el.closest('iframe')) {
            return {allowed: false, reason: 'blocklist'};
        }
        if (el.disabled || el.readOnly) {
            return {allowed: false, reason: 'blocklist'};
        }

        var tag = el.tagName.toLowerCase();
        var type = (el.getAttribute('type') || 'text').toLowerCase();

        if (tag !== 'textarea' && !(tag === 'input' && (type === 'text' || type === 'search'))) {
            return {allowed: false, reason: 'notallowlisted'};
        }
        if (BLOCKED_INPUT_TYPES.indexOf(type) !== -1) {
            return {allowed: false, reason: 'blocklist'};
        }

        var autocomplete = (el.getAttribute('autocomplete') || '').toLowerCase();
        if (BLOCKED_AUTOCOMPLETE_VALUES.indexOf(autocomplete) !== -1) {
            return {allowed: false, reason: 'blocklist'};
        }

        var identifiers = ((el.getAttribute('name') || '') + ' ' + (el.getAttribute('id') || '')).toLowerCase();
        var hasBlockedKeyword = BLOCKED_FIELD_NAME_KEYWORDS.some(function(word) {
            return identifiers.indexOf(word) !== -1;
        });
        if (hasBlockedKeyword) {
            return {allowed: false, reason: 'blocklist'};
        }

        return {allowed: true, reason: ''};
    };

    return {
        canClick: canClick,
        canSetValue: canSetValue,
        visibleLabel: visibleLabel
    };
});
