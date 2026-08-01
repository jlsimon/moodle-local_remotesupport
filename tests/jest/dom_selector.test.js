'use strict';

const loadAmd = require('./helpers/load-amd');

describe('local_remotesupport/dom_selector', () => {
    let DomSelector;

    beforeEach(() => {
        DomSelector = loadAmd('dom_selector');
        document.body.innerHTML = '';
    });

    describe('findClickableAncestor', () => {
        it('returns null for null/non-element input', () => {
            expect(DomSelector.findClickableAncestor(null)).toBeNull();
            expect(DomSelector.findClickableAncestor(document.createTextNode('x'))).toBeNull();
        });

        it('returns the element itself when it is directly clickable', () => {
            document.body.innerHTML = '<a href="#" id="link">Link</a>';
            const link = document.getElementById('link');
            expect(DomSelector.findClickableAncestor(link)).toBe(link);
        });

        it('returns the nearest clickable ancestor of a non-clickable descendant', () => {
            document.body.innerHTML = '<button id="btn"><span id="label">Click me</span></button>';
            const span = document.getElementById('label');
            const btn = document.getElementById('btn');
            expect(DomSelector.findClickableAncestor(span)).toBe(btn);
        });

        it('returns null when nothing clickable is in the ancestor chain', () => {
            document.body.innerHTML = '<div id="wrapper"><p id="text">Just text</p></div>';
            const p = document.getElementById('text');
            expect(DomSelector.findClickableAncestor(p)).toBeNull();
        });

        it('does not match a text input (clickable set excludes text fields)', () => {
            document.body.innerHTML = '<input type="text" id="field">';
            const field = document.getElementById('field');
            expect(DomSelector.findClickableAncestor(field)).toBeNull();
        });
    });

    describe('findPointableAncestor', () => {
        it('matches everything findClickableAncestor matches', () => {
            document.body.innerHTML = '<a href="#" id="link">Link</a>';
            const link = document.getElementById('link');
            expect(DomSelector.findPointableAncestor(link)).toBe(link);
        });

        it('also matches text fields, unlike findClickableAncestor', () => {
            document.body.innerHTML = '<textarea id="field"></textarea>';
            const field = document.getElementById('field');
            expect(DomSelector.findClickableAncestor(field)).toBeNull();
            expect(DomSelector.findPointableAncestor(field)).toBe(field);
        });

        it('does not match a password field', () => {
            document.body.innerHTML = '<input type="password" id="pwd">';
            const pwd = document.getElementById('pwd');
            expect(DomSelector.findPointableAncestor(pwd)).toBeNull();
        });
    });

    describe('buildRobustSelector', () => {
        it('prefers a stable id when the element has one', () => {
            document.body.innerHTML = '<div><button id="save-btn">Save</button></div>';
            const btn = document.getElementById('save-btn');
            expect(DomSelector.buildRobustSelector(btn)).toBe('#save-btn');
        });

        it('escapes CSS-special characters in an id', () => {
            document.body.innerHTML = '<div id="a:b.c"></div>';
            const el = document.getElementById('a:b.c');
            const selector = DomSelector.buildRobustSelector(el);
            expect(document.querySelector(selector)).toBe(el);
        });

        it('never anchors on the synthetic viewport-content wrapper id', () => {
            document.body.innerHTML =
                '<div id="' + DomSelector.VIEWPORT_CONTENT_ID + '"><button id="btn">Go</button></div>';
            const btn = document.getElementById('btn');
            const selector = DomSelector.buildRobustSelector(btn);
            expect(selector).not.toContain(DomSelector.VIEWPORT_CONTENT_ID);
            // Still resolves correctly relative to the wrapper's own subtree.
            const wrapper = document.getElementById(DomSelector.VIEWPORT_CONTENT_ID);
            expect(wrapper.querySelector(selector)).toBe(btn);
        });

        it('falls back to a structural nth-of-type path with no id anywhere', () => {
            document.body.innerHTML =
                '<div><p>one</p><p>two</p><p id="target-holder"><a href="#">link</a></p></div>';
            const link = document.querySelector('#target-holder a');
            const selector = DomSelector.buildRobustSelector(link);
            expect(document.querySelector(selector)).toBe(link);
        });

        it('disambiguates same-tag siblings by position, not just tag name', () => {
            document.body.innerHTML =
                '<div id="list"><a href="#">first</a><a href="#">second</a><a href="#">third</a></div>';
            const links = document.querySelectorAll('#list a');
            const selectors = Array.prototype.map.call(links, (el) => DomSelector.buildRobustSelector(el));
            // Every generated selector must resolve back to its own, distinct element.
            selectors.forEach((selector, i) => {
                expect(document.querySelector(selector)).toBe(links[i]);
            });
            expect(new Set(selectors).size).toBe(links.length);
        });

        it('stops climbing at the first ancestor with an id', () => {
            document.body.innerHTML =
                '<div id="stable-root"><div><div><span id="leaf-target"></span></div></div></div>';
            // Remove the id so buildRobustSelector must walk structurally up to #stable-root.
            const leaf = document.getElementById('leaf-target');
            leaf.removeAttribute('id');
            const selector = DomSelector.buildRobustSelector(leaf);
            expect(selector.indexOf('#stable-root')).toBe(0);
            expect(document.querySelector(selector)).toBe(leaf);
        });

        it('produces a selector rooted at the document when no ancestor has an id', () => {
            document.body.innerHTML = '<section><article><span id="x"></span></article></section>';
            const span = document.getElementById('x');
            span.removeAttribute('id');
            const selector = DomSelector.buildRobustSelector(span);
            expect(document.querySelector(selector)).toBe(span);
        });
    });
});
