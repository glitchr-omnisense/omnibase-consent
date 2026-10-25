// npm install && npm test   (node --test "tests/js/*.test.mjs"; needs jsdom)
//
// A page that still packs @glitchr/cookie (no @glitchr/flowjs/cookie on it):
// consent.js finds no library once the page's scripts have run and starts
// consent-legacy.js, the panel as it was, on the same partial. Among what it
// has to keep doing: a circle that loses its panel, or goes away - a page swap
// (the navigation, Turbo) empties the bar it sat in, a script rebuilds a
// footer. close() used to throw there ("Cannot set properties of null"), from
// an answer, a click elsewhere or Escape.
import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { circle as partial, closeAll, page as open, settings } from './page.mjs';

afterEach(closeAll);

const circle = (place) => partial({ place, features: [{ name: 'USER', implicit: true }, { name: 'CHAT' }] });
const page = (body, options = {}) => open(body, { library: false, ...options });

const click = (window, element) => element.dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));

test('without the library, the panel as it was: the calls kept are made, the answer goes where @glitchr/cookie kept it', async () => {
    const calls = [];
    const { window, document, Consent, errors } = await page(partial({ countdown: 10 }), {
        before: (window) => { window.Cookie = { set() {}, setConsent(consent, group) { window.localStorage.setItem('cookie/' + group, String(consent)); } }; },
        inline: (window) => { calls.push(window.Consent.use('CHAT', { label: 'Chat' }, () => calls.push('on'))); }
    });

    assert.deepEqual(errors, []);
    assert.deepEqual(calls, [false], 'asked before the page was ready: off, nothing run');
    assert.equal(document.querySelector('#circle-footer').classList.contains('is-floating'), true, 'the question floats');
    assert.equal(document.querySelector('#ga-cookie-footer').hidden, false);
    assert.equal(document.querySelector('.ga-cookie-count').textContent, '10');
    assert.equal(document.querySelector('[data-consent-section]').hidden, true, 'the detailed section of the library stays shut');
    assert.equal(document.querySelector('[data-consent-details]').hidden, false, 'two features: the old switches are offered');
    assert.equal(Consent.features().map((f) => f.name).join(), 'USER,CHAT');

    click(window, document.querySelector('[data-consent-details]'));
    assert.equal(document.querySelectorAll('.ga-cookie-features [data-consent-feature]').length, 2);
    click(window, document.querySelector('[data-consent-accept]'));
    assert.deepEqual(calls, [false, 'on']);
    assert.equal(window.localStorage.getItem('cookie/CHAT'), 'true');
    assert.equal(Consent.state('USER'), true);
    assert.equal(document.querySelector('#ga-cookie-footer').hidden, true);
});

test('one feature, without the library: no switch offered, as before', async () => {
    const { document } = await page(partial());
    assert.equal(document.querySelector('[data-consent-details]').hidden, true);
});

test('an answer closes the circles, one of which lost its panel', async () => {
    const { document, Consent, errors } = await page(circle('bar') + circle('footer'));
    document.querySelector('#ga-cookie-footer').remove();

    assert.doesNotThrow(() => Consent.accept());
    assert.deepEqual(errors, []);
    assert.equal(Consent.state('USER'), true);
    assert.equal(Consent.state('CHAT'), true);
    assert.equal(document.querySelector('#ga-cookie-bar').hidden, true);
});

test('an answer after the circle left the page', async () => {
    const { document, Consent, errors } = await page(circle('bar'));
    const widget = document.querySelector('#circle-bar');
    widget.querySelector('.ga-cookie-panel').remove();
    widget.remove();

    assert.doesNotThrow(() => Consent.refuse());
    assert.doesNotThrow(() => Consent.open());
    assert.doesNotThrow(() => Consent.close());
    assert.deepEqual(errors, []);
    assert.equal(Consent.state('USER'), false);
});

test('a click elsewhere and Escape, the panel gone', async () => {
    const { window, document, Consent, errors } = await page(circle('bar') + '<main id="elsewhere"></main><a data-consent-open id="link"></a>');
    Consent.accept(); // answered: the circle no longer floats
    document.querySelector('#ga-cookie-bar').remove();

    click(window, document.querySelector('#elsewhere'));
    click(window, document.querySelector('#link'));
    click(window, document.querySelector('[data-consent-toggle]'));
    document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

    assert.deepEqual(errors, []);
});

test('the panel still opens and closes', async () => {
    const { window, document, Consent } = await page(circle('bar'));
    Consent.accept();
    const panel = document.querySelector('#ga-cookie-bar');
    const toggle = document.querySelector('[data-consent-toggle]');

    click(window, toggle);
    assert.equal(panel.hidden, false);
    assert.equal(toggle.getAttribute('aria-expanded'), 'true');

    Consent.close();
    assert.equal(panel.hidden, true);
    assert.equal(toggle.getAttribute('aria-expanded'), 'false');

    Consent.open();
    assert.equal(panel.hidden, false);
    click(window, toggle);
    assert.equal(panel.hidden, true);
});

test('a click elsewhere answers only if consent.dismiss says so, never one on the toolbar', async () => {
    const body = (dismiss) => partial({ place: 'bar', features: [{ name: 'USER', implicit: true }, { name: 'CHAT' }], config: settings({ dismiss }) })
        + '<div class="sf-toolbar"><div id="sfwdt1"><button id="tool"></button></div></div><main id="elsewhere"></main>';

    const waits = await page(body('none'));
    click(waits.window, waits.document.querySelector('#elsewhere'));
    assert.equal(waits.Consent.undecided(), true, 'dismiss: none - the question stays');

    // jsdom lays nothing out: the floating panel is taken for one the visitor can see.
    const answers = await page(body('default'), { before: (window) => { window.Element.prototype.checkVisibility = () => true; } });
    click(answers.window, answers.document.querySelector('#tool'));
    assert.equal(answers.Consent.undecided(), true, 'the toolbar is not the page');
    click(answers.window, answers.document.querySelector('#elsewhere'));
    assert.equal(answers.Consent.undecided(), false, 'dismiss: default - a click elsewhere takes the implicit answer');
    assert.equal(answers.Consent.state('USER'), true);
    assert.equal(answers.Consent.state('CHAT'), false);
});

test('without the library, countdown_action: refuse - no answer refuses every feature but the required ones', async () => {
    const config = settings({ countdown: { duration: 1, action: 'refuse' } });
    const { document, Consent } = await page(partial({ countdown: 1, config, features: [{ name: 'NECESSARY', required: true }, { name: 'USER', implicit: true }, { name: 'CHAT' }] }), {
        before: (window) => { window.HTMLElement.prototype.checkVisibility = () => true; }
    });
    assert.equal(document.querySelector('#circle-footer').getAttribute('data-consent-action'), 'refuse');
    await new Promise((resolve) => setTimeout(resolve, 1900));
    assert.equal(Consent.state('NECESSARY'), true);
    assert.equal(Consent.state('USER'), false, 'the implicit feature refused, as the panel said');
    assert.equal(Consent.state('CHAT'), false);
    assert.equal(document.querySelector('#ga-cookie-footer').hidden, true);
});
