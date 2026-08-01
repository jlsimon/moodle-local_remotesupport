'use strict';

const loadAmd = require('./helpers/load-amd');

/**
 * A minimal stand-in for an <iframe>: screen_renderer.js only ever reads/
 * writes iframe.style.*, iframe.srcdoc, iframe.onload, iframe.contentDocument
 * and calls iframe.getBoundingClientRect() — a plain object is both simpler
 * and more reliable than a real jsdom <iframe>, whose srcdoc/contentDocument
 * navigation is not fully simulated by jsdom.
 *
 * @return {Object}
 */
function createFakeIframe() {
    return {
        style: {},
        srcdoc: '',
        onload: null,
        contentDocument: null,
        getBoundingClientRect: () => ({left: 0, top: 0, width: 0, height: 0})
    };
}

describe('local_remotesupport/screen_renderer', () => {
    let ScreenRenderer;
    let iframe;
    let wrapper;
    let renderer;

    beforeEach(() => {
        ScreenRenderer = loadAmd('screen_renderer');
        iframe = createFakeIframe();
        wrapper = document.createElement('div');
        document.body.appendChild(wrapper);
        renderer = ScreenRenderer.create(iframe, wrapper);
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    describe('applyViewportSize', () => {
        it('ignores a missing or malformed viewport', () => {
            renderer.applyViewportSize(null);
            renderer.applyViewportSize({width: 'x', height: 100});
            renderer.applyViewportSize({width: 0, height: 100});
            expect(iframe.style.width).toBeUndefined();
        });

        it('clamps the viewport size to [200, 4000]', () => {
            renderer.applyViewportSize({width: 50, height: 9000});
            expect(iframe.style.width).toBe('200px');
            expect(iframe.style.height).toBe('4000px');
        });

        it('scales the iframe down to fit the wrapper, keeping its native size', () => {
            Object.defineProperty(wrapper, 'clientWidth', {value: 400, configurable: true});
            renderer.applyViewportSize({width: 800, height: 600});
            expect(iframe.style.width).toBe('800px');
            expect(iframe.style.height).toBe('600px');
            expect(iframe.style.transform).toBe('scale(0.5)');
            expect(wrapper.style.height).toBe('300px');
        });

        it('never scales up past 1 when the wrapper is wider than the content', () => {
            Object.defineProperty(wrapper, 'clientWidth', {value: 2000, configurable: true});
            renderer.applyViewportSize({width: 800, height: 600});
            expect(iframe.style.transform).toBe('scale(1)');
        });
    });

    describe('applyCursorPosition / hideCursor', () => {
        it('creates and positions a cursor element inside the wrapper', () => {
            renderer.applyCursorPosition(10, 20);
            const cursor = wrapper.querySelector('.local-remotesupport-student-cursor');
            expect(cursor).not.toBeNull();
            expect(cursor.style.left).toBe('10px');
            expect(cursor.style.top).toBe('20px');
        });

        it('scales the cursor position by the last computed viewport scale', () => {
            Object.defineProperty(wrapper, 'clientWidth', {value: 400, configurable: true});
            renderer.applyViewportSize({width: 800, height: 600});
            renderer.applyCursorPosition(100, 50);
            const cursor = wrapper.querySelector('.local-remotesupport-student-cursor');
            expect(cursor.style.left).toBe('50px');
            expect(cursor.style.top).toBe('25px');
        });

        it('ignores non-numeric coordinates', () => {
            renderer.applyCursorPosition('a', 'b');
            expect(wrapper.querySelector('.local-remotesupport-student-cursor')).toBeNull();
        });

        it('hides the cursor element and clears any highlight', () => {
            iframe.contentDocument = document.implementation.createHTMLDocument('');
            iframe.contentDocument.body.innerHTML = '<button id="target">Go</button>';
            renderer.applyCursorPosition(1, 1, '#target');
            expect(iframe.contentDocument.getElementById('target').classList
                .contains('local-remotesupport-hover-highlight')).toBe(true);

            renderer.hideCursor();
            const cursor = wrapper.querySelector('.local-remotesupport-student-cursor');
            expect(cursor.style.display).toBe('none');
            expect(iframe.contentDocument.getElementById('target').classList
                .contains('local-remotesupport-hover-highlight')).toBe(false);
        });
    });

    describe('applyCursorPosition hover/typing highlight', () => {
        beforeEach(() => {
            iframe.contentDocument = document.implementation.createHTMLDocument('');
            iframe.contentDocument.body.innerHTML =
                '<button id="hoverable">Hover</button><input id="typing" type="text">';
        });

        it('adds a hover-highlight class to the element the selector resolves to', () => {
            renderer.applyCursorPosition(5, 5, '#hoverable');
            expect(iframe.contentDocument.getElementById('hoverable').classList
                .contains('local-remotesupport-hover-highlight')).toBe(true);
        });

        it('adds a typing-highlight class independently of the hover one', () => {
            renderer.applyCursorPosition(5, 5, '#hoverable', '#typing');
            expect(iframe.contentDocument.getElementById('hoverable').classList
                .contains('local-remotesupport-hover-highlight')).toBe(true);
            expect(iframe.contentDocument.getElementById('typing').classList
                .contains('local-remotesupport-typing-highlight')).toBe(true);
        });

        it('clears the previous highlight before applying a new one', () => {
            renderer.applyCursorPosition(5, 5, '#hoverable');
            renderer.applyCursorPosition(5, 5, null);
            expect(iframe.contentDocument.getElementById('hoverable').classList
                .contains('local-remotesupport-hover-highlight')).toBe(false);
        });

        it('does not throw on a malformed/stale selector', () => {
            expect(() => renderer.applyCursorPosition(5, 5, '###not-a-selector')).not.toThrow();
        });

        it('recenters the cursor dot on the highlighted element, overriding the raw coordinate', () => {
            const el = iframe.contentDocument.getElementById('hoverable');
            el.getBoundingClientRect = () => ({left: 100, top: 200, width: 20, height: 10});
            renderer.applyCursorPosition(1, 1, '#hoverable');
            const cursor = wrapper.querySelector('.local-remotesupport-student-cursor');
            // Center of the rect: x = 100 + 20/2 = 110, y = 200 + 10/2 = 205.
            expect(cursor.style.left).toBe('110px');
            expect(cursor.style.top).toBe('205px');
        });
    });

    describe('showClickMark', () => {
        beforeEach(() => jest.useFakeTimers());
        afterEach(() => jest.useRealTimers());

        it('adds a transient click-mark element and removes it after the animation', () => {
            renderer.showClickMark(30, 40);
            let mark = wrapper.querySelector('.local-remotesupport-click-mark');
            expect(mark).not.toBeNull();
            expect(mark.style.left).toBe('30px');

            jest.advanceTimersByTime(600);
            mark = wrapper.querySelector('.local-remotesupport-click-mark');
            expect(mark).toBeNull();
        });

        it('ignores non-numeric coordinates', () => {
            renderer.showClickMark(undefined, undefined);
            expect(wrapper.querySelector('.local-remotesupport-click-mark')).toBeNull();
        });
    });

    describe('renderPage', () => {
        it('wraps the payload html in the synthetic viewport-content id', () => {
            renderer.renderPage({html: '<p>Hello</p>', viewport: {width: 800, height: 600}});
            expect(iframe.srcdoc).toContain('<div id="local-remotesupport-viewport-content"><p>Hello</p></div>');
        });

        it('only includes stylesheet links pointing at this site (M.cfg.wwwroot)', () => {
            renderer.renderPage({
                html: '',
                viewport: {width: 800, height: 600},
                css: ['http://example.com/moodle/theme/styles.css', 'https://evil.example/tracker.css']
            });
            expect(iframe.srcdoc).toContain('http://example.com/moodle/theme/styles.css');
            expect(iframe.srcdoc).not.toContain('evil.example');
        });

        it('escapes a literal </style> sequence inside inline CSS', () => {
            renderer.renderPage({
                html: '',
                viewport: {width: 800, height: 600},
                inlineCss: 'body{}</style><script>alert(1)</script>'
            });
            expect(iframe.srcdoc).not.toContain('</style><script>');
            expect(iframe.srcdoc).toContain('<\\/style>');
        });

        it('places modal and fixed html as siblings of the scrollable wrapper, not inside it', () => {
            renderer.renderPage({
                html: '<p>main</p>',
                viewport: {width: 800, height: 600},
                modal: '<div class="modal-html">M</div>',
                fixed: '<nav class="fixed-html">N</nav>'
            });
            const contentIndex = iframe.srcdoc.indexOf('local-remotesupport-viewport-content');
            const wrapperCloseIndex = iframe.srcdoc.indexOf('</div>', contentIndex);
            const modalIndex = iframe.srcdoc.indexOf('modal-html');
            const fixedIndex = iframe.srcdoc.indexOf('fixed-html');
            expect(modalIndex).toBeGreaterThan(wrapperCloseIndex);
            expect(fixedIndex).toBeGreaterThan(wrapperCloseIndex);
        });

        it('hides the cursor on a real navigation but keeps it across a same-page refresh', () => {
            renderer.applyCursorPosition(5, 5);
            renderer.renderPage({html: '', viewport: {width: 800, height: 600}, url: '/course/view.php?id=2'});
            let cursor = wrapper.querySelector('.local-remotesupport-student-cursor');
            expect(cursor.style.display).toBe('none');

            renderer.applyCursorPosition(5, 5);
            renderer.renderPage({html: '', viewport: {width: 800, height: 600}, url: '/course/view.php?id=2'});
            cursor = wrapper.querySelector('.local-remotesupport-student-cursor');
            expect(cursor.style.display).not.toBe('none');
        });
    });

    describe('startPicking / stopPicking', () => {
        it('invokes the callback with a selector when a pointable element is clicked', () => {
            iframe.contentDocument = document.implementation.createHTMLDocument('');
            iframe.contentDocument.body.innerHTML = '<button id="pick-me">Pick</button>';
            const target = iframe.contentDocument.getElementById('pick-me');
            iframe.contentDocument.elementFromPoint = () => target;

            const onPick = jest.fn();
            renderer.startPicking(onPick);
            wrapper.dispatchEvent(new window.MouseEvent('click', {clientX: 1, clientY: 1}));

            expect(onPick).toHaveBeenCalledWith('#pick-me');
        });

        it('does nothing once stopPicking() has been called', () => {
            iframe.contentDocument = document.implementation.createHTMLDocument('');
            iframe.contentDocument.body.innerHTML = '<button id="pick-me">Pick</button>';
            iframe.contentDocument.elementFromPoint = () => iframe.contentDocument.getElementById('pick-me');

            const onPick = jest.fn();
            renderer.startPicking(onPick);
            renderer.stopPicking();
            wrapper.dispatchEvent(new window.MouseEvent('click', {clientX: 1, clientY: 1}));

            expect(onPick).not.toHaveBeenCalled();
        });
    });
});
