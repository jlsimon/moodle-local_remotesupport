'use strict';

// Moodle's core global object. Real AMD modules only ever read
// M.cfg.wwwroot, so that is all this stub provides.
global.M = {
    cfg: {
        wwwroot: 'http://example.com/moodle'
    }
};

// jsdom does not implement CSS.escape (https://github.com/jsdom/jsdom/issues/1550).
// dom_selector.js needs it to build id-based selectors, so polyfill it here
// with the standard CSSOM algorithm (https://drafts.csswg.org/cssom/#serialize-an-identifier).
if (typeof global.CSS === 'undefined') {
    global.CSS = {};
}
if (typeof global.CSS.escape !== 'function') {
    global.CSS.escape = function(value) {
        const string = String(value);
        const length = string.length;
        let result = '';
        for (let index = 0; index < length; index++) {
            const codeUnit = string.charCodeAt(index);
            if (codeUnit === 0x0000) {
                result += '�';
                continue;
            }
            if ((codeUnit >= 0x0001 && codeUnit <= 0x001F) || codeUnit === 0x007F ||
                    (index === 0 && codeUnit >= 0x0030 && codeUnit <= 0x0039) ||
                    (index === 1 && codeUnit >= 0x0030 && codeUnit <= 0x0039 &&
                        string.charCodeAt(0) === 0x002D)) {
                result += '\\' + codeUnit.toString(16) + ' ';
                continue;
            }
            if (index === 0 && length === 1 && codeUnit === 0x002D) {
                result += '\\' + string.charAt(index);
                continue;
            }
            if (codeUnit >= 0x0080 || codeUnit === 0x002D || codeUnit === 0x005F ||
                    (codeUnit >= 0x0030 && codeUnit <= 0x0039) ||
                    (codeUnit >= 0x0041 && codeUnit <= 0x005A) ||
                    (codeUnit >= 0x0061 && codeUnit <= 0x007A)) {
                result += string.charAt(index);
                continue;
            }
            result += '\\' + string.charAt(index);
        }
        return result;
    };
}
