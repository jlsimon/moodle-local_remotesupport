'use strict';

const fs = require('fs');
const path = require('path');

const AMD_SRC_DIR = path.resolve(__dirname, '../../../amd/src');

/**
 * Loads a Moodle AMD module (amd/src/*.js, using the plain
 * define([...], function() {...}) RequireJS format) for testing, without
 * a real RequireJS/AMD loader.
 *
 * Local (local_remotesupport/xxx) dependencies are resolved by loading the
 * sibling amd/src/xxx.js file recursively. Everything else (core/ajax,
 * core/templates, ...) must be supplied via the `mocks` map, keyed by its
 * AMD module id.
 *
 * @param {String} name Module name without extension, e.g. 'dom_selector'.
 * @param {Object} [mocks] AMD id => stub value, for non-local dependencies.
 * @return {*} Whatever the module's factory function returned.
 */
function loadAmd(name, mocks) {
    mocks = mocks || {};
    const absPath = path.join(AMD_SRC_DIR, name + '.js');
    const code = fs.readFileSync(absPath, 'utf8');

    let result;
    const define = function(deps, factory) {
        if (typeof deps === 'function') {
            factory = deps;
            deps = [];
        }
        const resolved = deps.map(function(dep) {
            if (Object.prototype.hasOwnProperty.call(mocks, dep)) {
                return mocks[dep];
            }
            if (dep.indexOf('local_remotesupport/') === 0) {
                return loadAmd(dep.slice('local_remotesupport/'.length), mocks);
            }
            throw new Error('load-amd: no mock provided for AMD dependency "' + dep + '"');
        });
        result = factory.apply(null, resolved);
    };

    // Runs in the current (jsdom) realm via Node's Function constructor, so
    // the module body sees the same window/document Jest's test file does
    // — no separate vm sandbox needed, just a fake define() in scope.
    const wrapper = new Function('define', code); // eslint-disable-line no-new-func
    wrapper(define);
    return result;
}

module.exports = loadAmd;
