// npm install && npm test   (node --test "tests/js/*.test.mjs"; needs jsdom and @glitchr/flowjs)
//
// consent.js in front of @glitchr/flowjs/cookie, on the partial itself: the
// question floats under the first circle and offers "Personnaliser" whatever
// the number of features; the detailed section lists them with what each
// keeps; a refusal erases and keeps from writing, a yes writes again; the
// answers of the visitors who gave them to @glitchr/cookie are taken over, a
// feature split from another one included; window.Consent answers before the
// library is there and after; several circles share the one panel.
import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { circle, closeAll, page, settings } from './page.mjs';

afterEach(closeAll);

const info = (window) => window.Cookie.set('USER', 'INFO', { locale: 'fr' }, 3600);
const zone = (window) => window.Cookie.set('LOCATION', 'TIMEZONE', 'Europe/Paris', 3600);

test('a first visit: the question floats, counted down, and offers to customize', async () => {
    const { document, Consent, Cookie, errors, $ } = await page(circle({ countdown: 10, config: settings({ countdown: { duration: 10, action: 'default' } }) }));

    assert.deepEqual(errors, []);
    assert.equal(Cookie.flow, true);
    assert.equal(Consent.undecided(), true);
    assert.equal($('#circle-footer').classList.contains('is-floating'), true);
    assert.equal($('#ga-cookie-footer').hidden, false);
    assert.equal(Cookie.banner(), $('#ga-cookie-footer'), 'the partial\'s panel is the library\'s banner');
    assert.equal($('[data-consent-summary]').hidden, false);
    assert.equal($('[data-consent-section]').hidden, true);
    assert.equal($('[data-consent-details]').hidden, false, '"Personnaliser" is there with one feature to answer for');
    assert.equal($('[data-consent-counting]').hidden, false);
    assert.equal($('.ga-cookie-count').textContent, '10');
    assert.equal($('[data-consent-toggle]').getAttribute('aria-expanded'), 'true');
    assert.equal($('.ga-cookie-features').hidden, true, 'the old switches stay shut');
    assert.equal(Consent.enabled('USER'), true, 'implicit: on until refused');
    assert.equal(Consent.state('USER'), null);
    assert.equal(Consent.state('NECESSARY'), true);
    assert.equal(document.querySelectorAll('[data-consent-banner]').length, 1);
    assert.equal(document.querySelectorAll('[data-consent-accept]').length, 1, 'one "accept" and one "refuse" by circle: the question\'s');
    assert.equal(document.querySelectorAll('[data-consent-refuse]').length, 1);
});

test('"Tout refuser" and "Tout accepter" of the detailed section', async () => {
    const { Consent, click, $ } = await page(circle(), { local: { 'cookie/USER': 'true' } });

    click('[data-consent-toggle]');
    assert.equal($('[data-consent-section]').hidden, false, 'answered: the circle opens the detailed section');
    click('[data-consent-all="refuse"]');
    assert.equal(Consent.state('USER'), false);
    assert.equal(Consent.state('LOCATION'), false);
    assert.equal(Consent.state('NECESSARY'), true);
    assert.equal($('#ga-cookie-footer').hidden, true);
    click('[data-consent-toggle]');
    click('[data-consent-all="accept"]');
    assert.equal(Consent.state('USER'), true);
    assert.equal(Consent.state('LOCATION'), true);
    assert.equal($('#ga-cookie-footer').hidden, true);
});

test('"Personnaliser": a row per feature, its switch, what it keeps in the visitor\'s words', async () => {
    const { $, click, document, Consent } = await page(circle());

    click('[data-consent-details]');
    assert.equal($('[data-consent-section]').hidden, false);
    assert.equal($('[data-consent-summary]').hidden, true);
    assert.equal($('#circle-footer').classList.contains('is-choosing'), true);
    const rows = Array.from(document.querySelectorAll('[data-consent-categories] > li'));
    assert.equal(rows.map((row) => row.getAttribute('data-consent-row')).join(), 'NECESSARY,USER,LOCATION', 'ANALYTICS waits for a script to use it');
    const necessary = rows[0].querySelector('[data-consent-switch]');
    assert.equal(necessary.checked && necessary.disabled, true);
    assert.equal(rows[0].querySelector('[data-consent-required]').hidden, false);
    assert.equal(rows[0].querySelector('[data-consent-required]').textContent, '[required]');
    assert.equal(rows[1].querySelector('[data-consent-required]').hidden, true);
    assert.equal(rows[1].querySelector('[data-consent-label]').textContent, 'Preferences');
    assert.equal(rows[1].querySelector('[data-consent-description]').textContent, 'Language and theme.');
    assert.equal(rows[1].querySelector('[data-consent-switch]').checked, true);
    assert.equal(rows[1].querySelector('[data-consent-entry-name]').textContent, 'USER/INFO');
    assert.equal(rows[1].querySelector('[data-consent-entry-duration]').textContent, '30 days', 'the duration in words, not "30d"');
    assert.equal(rows[0].querySelector('[data-consent-entry-duration]').textContent, 'the visit');
    assert.equal(rows[1].querySelector('[data-consent-entry-purpose]').textContent, 'The browser.');

    // Only the time zone refused.
    rows[2].querySelector('[data-consent-switch]').click();
    click('[data-consent-keep]');
    assert.equal(Consent.state('USER'), true);
    assert.equal(Consent.state('LOCATION'), false);
    assert.equal(Consent.undecided(), false);
    assert.equal($('#ga-cookie-footer').hidden, true);
    assert.equal($('#circle-footer').classList.contains('is-floating'), false);
    assert.equal($('#circle-footer').classList.contains('is-choosing'), false);
});

test('refused, a feature writes nothing and what it kept is erased; accepted, it writes again', async () => {
    const heard = [];
    const { window, document, Consent, click, has, $ } = await page(circle(), { inline: (window) => { info(window); zone(window); } });
    window.addEventListener('consent:change', (event) => heard.push('change ' + JSON.stringify(event.detail.features) + ' ' + event.detail.consent));
    window.addEventListener('ga:cookie', () => heard.push('ga'));
    document.addEventListener('user:consent', () => heard.push('user'));

    assert.equal(has('USER/INFO') && has('LOCATION/TIMEZONE'), true, 'written while nothing is refused');

    click('[data-consent-refuse]');
    assert.equal(has('USER/INFO') || has('LOCATION/TIMEZONE'), false, 'erased at the refusal');
    info(window); zone(window);
    assert.equal(has('USER/INFO') || has('LOCATION/TIMEZONE'), false, 'and not written again');
    assert.equal(Consent.cookie('USER', 'THEME', 'dark', 3600), false);
    assert.equal(Consent.refused(), true);
    assert.equal(Consent.accepted(), false);
    assert.equal($('#circle-footer').classList.contains('is-refused'), true);
    assert.equal($('[data-consent-toggle]').title, '[refused]');
    assert.equal(window.localStorage.getItem('cookie/USER'), 'false', 'kept where @glitchr/cookie kept it too');
    assert.equal(heard.join(' | '), 'change {"NECESSARY":true,"USER":false,"LOCATION":false,"ANALYTICS":false} false | ga');

    heard.length = 0;
    // The circle opens the detailed section, the switches as refused; "Tout accepter" there.
    click('[data-consent-toggle]');
    assert.equal($('#ga-cookie-footer').hidden, false);
    assert.equal($('#circle-footer').classList.contains('is-floating'), false, 'answered: under its circle, not floating');
    assert.equal($('[data-consent-switch="USER"]').checked, false);
    click('[data-consent-all="accept"]');
    assert.equal(heard[0], 'user', 'user:consent for the scripts that wait for it');
    info(window); zone(window);
    assert.equal(has('USER/INFO') && has('LOCATION/TIMEZONE'), true);
    assert.equal(Consent.cookie('USER', 'THEME', 'dark', 3600), true);
    assert.equal(has('USER/THEME'), true);
    assert.equal(Consent.refused(), false);
    assert.equal($('[data-consent-toggle]').title, '[accepted]');
});

test('one feature refused in the detailed section: the other keeps writing', async () => {
    const { window, click, has, document } = await page(circle(), { inline: (window) => { info(window); zone(window); } });

    click('[data-consent-details]');
    document.querySelector('[data-consent-switch="LOCATION"]').click();
    click('[data-consent-keep]');
    assert.equal(has('USER/INFO'), true);
    assert.equal(has('LOCATION/TIMEZONE'), false);
    zone(window); info(window);
    assert.equal(has('LOCATION/TIMEZONE'), false);
    assert.equal(has('USER/INFO'), true);
});

test('an answer given to @glitchr/cookie is taken over: nobody is asked again, the feature split from USER included', async () => {
    const yes = await page(circle({ countdown: 10, config: settings({ countdown: { duration: 10, action: 'default' } }) }), { local: { 'cookie/USER': 'true', 'consent/answered': '2026-01-01' } });
    assert.equal(yes.Consent.undecided(), false);
    assert.equal(yes.$('#ga-cookie-footer').hidden, true);
    assert.equal(yes.$('#circle-footer').classList.contains('is-floating'), false);
    assert.equal(yes.Consent.state('USER'), true);
    assert.equal(yes.Consent.state('LOCATION'), true, 'the time zone was part of USER');
    assert.equal(yes.errors.length, 0);

    const no = await page(circle(), { local: { 'cookie/USER': 'false' }, inline: (window) => { info(window); zone(window); } });
    assert.equal(no.Consent.undecided(), false);
    assert.equal(no.Consent.refused(), true);
    assert.equal(no.Consent.state('LOCATION'), false);
    assert.equal(no.has('USER/INFO') || no.has('LOCATION/TIMEZONE'), false);
    assert.equal(no.$('#circle-footer').classList.contains('is-refused'), true);
});

test('window.Consent before the library is there: the calls are kept, answered from what the browser holds', async () => {
    const calls = [];
    const { Consent, click, document, $ } = await page(circle(), {
        local: { 'cookie/USER': 'true' },
        before: (window) => {
            // A script of the page, deferred before the core's: Consent is not there yet.
            document_ready(window, () => calls.push('captcha ' + window.Consent.use('CAPTCHA', { label: 'The captcha' }, () => calls.push('captcha on'), () => calls.push('captcha off'))));
        },
        inline: (window) => {
            calls.push('user ' + window.Consent.use('USER', () => calls.push('user on')));
            calls.push('state ' + window.Consent.state('USER') + ' ' + window.Consent.enabled('USER') + ' ' + window.Consent.refused());
        }
    });

    assert.deepEqual(calls, ['user true', 'state true true false', 'user on', 'captcha false'], 'kept, then made once the library is there; a listener of DOMContentLoaded finds it ready');
    assert.equal(Consent.undecided(), false, 'a feature a script declares is no reason to ask again');
    click('[data-consent-toggle]');
    assert.equal($('[data-consent-row="CAPTCHA"] [data-consent-label]').textContent, 'The captcha', 'its switch is there');
    $('[data-consent-switch="CAPTCHA"]').click();
    click('[data-consent-keep]');
    assert.equal(calls.at(-1), 'captcha on');
    assert.equal(Consent.enabled('CAPTCHA'), true);
    Consent.refuse();
    assert.equal(calls.at(-1), 'captcha off');
    assert.equal(document.querySelector('#ga-cookie-footer').hidden, true);

    function document_ready(window, fn) { window.document.addEventListener('DOMContentLoaded', fn); }
});

test('a group nobody declared becomes a feature, unwritten until the visitor says yes', async () => {
    const { window, Consent, has, click, $ } = await page(circle(), { local: { 'cookie/USER': 'true' } });

    window.Cookie.set('CHAT', 'ID', 'abc', 3600);
    assert.equal(has('CHAT/ID'), false);
    assert.equal(Consent.features().map((f) => f.name + ':' + f.enabled).join(), 'NECESSARY:true,USER:true,LOCATION:true,CHAT:false');
    assert.equal(Consent.cookie('STATS', 'ID', 'abc', 3600), false);
    assert.equal(Consent.cookie('ANALYTICS', 'VISITOR_ID', 'abc', 3600), false, 'known to the site, off until asked for');
    Consent.accept();
    window.Cookie.set('CHAT', 'ID', 'abc', 3600);
    assert.equal(has('CHAT/ID'), true);
    assert.equal(Consent.cookie('ANALYTICS', 'VISITOR_ID', 'abc', 3600), true);
    click('[data-consent-toggle]');
    assert.equal($('[data-consent-row="ANALYTICS"] [data-consent-label]').textContent, 'Audience', 'with the site\'s words');
    assert.equal($('[data-consent-row="STATS"] [data-consent-label]').textContent, 'Stats');
});

test('the elements that wait for a feature wake at its yes', async () => {
    const { window, Consent, document } = await page(circle() + '<script type="text/plain" data-consent-script="ANALYTICS">window.__ran = (window.__ran || 0) + 1;</script><iframe data-consent-script="SOCIAL" data-consent-src="about:blank#embed"></iframe>');
    const heard = [];
    document.addEventListener('social:consent', () => heard.push('social'));

    assert.equal(window.__ran, undefined);
    assert.equal(document.querySelector('iframe').hasAttribute('src'), false);
    Consent.accept();
    assert.equal(document.querySelector('iframe').getAttribute('src'), 'about:blank#embed');
    assert.equal(document.querySelector('script[data-consent-script]').type, '', 'the inert script is a script now');
    assert.deepEqual(heard, ['social']);
});

test('several circles share the panel: under the first while it floats, under the one clicked afterwards', async () => {
    const { Cookie, Consent, click, key, $, document, errors } = await page(circle({ place: 'bar' }) + circle({ place: 'footer' }) + '<main id="elsewhere"></main><a href="#" data-consent-open id="link">cookies</a>');

    assert.equal(Cookie.banner().parentNode, $('#circle-bar'));
    assert.equal($('#circle-bar').classList.contains('is-floating'), true);
    assert.equal($('#circle-footer').classList.contains('is-floating'), false);
    click('#circle-bar [data-consent-accept]');
    assert.equal(Consent.undecided(), false);

    click('#circle-footer [data-consent-toggle]');
    assert.equal(Cookie.banner().parentNode, $('#circle-footer'), 'the live panel moved under the circle clicked');
    assert.equal(Cookie.banner().hidden, false);
    assert.equal(Cookie.banner().id, 'ga-cookie-footer');
    assert.equal($('#circle-footer [data-consent-toggle]').getAttribute('aria-expanded'), 'true');
    assert.equal($('#circle-bar [data-consent-toggle]').getAttribute('aria-expanded'), 'false');
    assert.equal($('#circle-bar').querySelectorAll('.ga-cookie-panel').length, 1, 'each circle keeps a panel');
    assert.equal($('#circle-bar .ga-cookie-panel').hidden, true);
    assert.equal(document.querySelectorAll('#ga-cookie-footer, #ga-cookie-bar').length, 2);

    click('#circle-footer [data-consent-toggle]');
    assert.equal(Cookie.banner().hidden, true, 'the circle closes its panel');
    click('#link');
    assert.equal(Cookie.banner().hidden, false, 'a "Cookies" link opens it');
    assert.equal(Cookie.banner().parentNode, $('#circle-bar'), 'under the first circle');
    click('#elsewhere');
    assert.equal(Cookie.banner().hidden, true, 'a click elsewhere closes it');
    Consent.open();
    key('Escape');
    assert.equal(Cookie.banner().hidden, true, 'Escape too');
    assert.equal(Consent.state('USER'), true, 'none of which is an answer');

    // The bar leaves the page with the panel (a page swap, a script): the footer's takes over.
    Consent.open();
    $('#circle-bar').remove();
    Consent.scan();
    Consent.open();
    assert.equal(Cookie.banner().parentNode, $('#circle-footer'));
    assert.equal(Cookie.banner().hidden, false);
    Consent.refuse();
    assert.equal(Consent.refused(), true);
    assert.deepEqual(errors, []);
});

test('answered by the countdown, the circle and a "Cookies" link show what was recorded: the switches, "Tout accepter", "Tout refuser", "Fermer"', async () => {
    const { Consent, click, key, $ } = await page(circle({ countdown: 1, config: settings({ countdown: { duration: '300ms', action: 'default' } }) }) + '<script type="text/plain" data-consent-script="ANALYTICS"></script><a href="#" data-consent-open id="link">cookies</a><main id="elsewhere"></main>');
    await new Promise((resolve) => setTimeout(resolve, 700));
    assert.equal(Consent.undecided(), false, 'the countdown answered');
    assert.equal($('#ga-cookie-footer').hidden, true);

    click('[data-consent-toggle]');
    assert.equal($('#ga-cookie-footer').hidden, false);
    assert.equal($('[data-consent-section]').hidden, false, 'one click: the detailed section');
    assert.equal($('[data-consent-summary]').hidden, true, 'not the question again');
    assert.equal($('#circle-footer').classList.contains('is-choosing'), true);
    assert.equal($('[data-consent-switch="NECESSARY"]').checked, true);
    assert.equal($('[data-consent-switch="USER"]').checked, true, 'as recorded: the implicit features on');
    assert.equal($('[data-consent-switch="LOCATION"]').checked, true);
    assert.equal($('[data-consent-switch="ANALYTICS"]').checked, false, 'the tracker off');
    assert.equal($('[data-consent-all="accept"]').hidden, false);
    assert.equal($('[data-consent-all="refuse"]').hidden, false);

    click('[data-consent-close]');
    assert.equal($('#ga-cookie-footer').hidden, true, '"Fermer" closes it');
    assert.equal(Consent.state('ANALYTICS'), false, 'and answers nothing');

    click('#link');
    assert.equal($('[data-consent-section]').hidden, false, 'a "Cookies" link opens it the same way');
    click('[data-consent-all="refuse"]');
    assert.equal(Consent.refused(), true);
    assert.equal($('#ga-cookie-footer').hidden, true);
    click('[data-consent-toggle]');
    assert.equal($('[data-consent-switch="USER"]').checked, false, 'opened again, the refusal');
    click('[data-consent-all="accept"]');
    assert.equal(Consent.state('ANALYTICS'), true);
    Consent.open();
    assert.equal($('[data-consent-switch="ANALYTICS"]').checked, true);
    key('Escape');
    assert.equal($('#ga-cookie-footer').hidden, true, 'Escape closes it too');
    click('[data-consent-toggle]');
    click('#elsewhere');
    assert.equal($('#ga-cookie-footer').hidden, true, 'and a click elsewhere');

    // consent.reopen: banner - the question again, the switches one click further.
    const asked = await page(circle({ config: settings({ reopen: 'banner' }) }), { local: { 'cookie/USER': 'true' } });
    asked.click('[data-consent-toggle]');
    assert.equal(asked.$('[data-consent-summary]').hidden, false);
    assert.equal(asked.$('[data-consent-section]').hidden, true);
});

test('a circle that must not float leaves the question to the other', async () => {
    const { Cookie, $ } = await page(circle({ place: 'bar', floating: false }) + circle({ place: 'footer' }));
    assert.equal(Cookie.banner().parentNode, $('#circle-footer'));
    assert.equal($('#circle-footer').classList.contains('is-floating'), true);
});

test('no answer: when the time is up the implicit features are on, the rest off; a click elsewhere answers only if consent.dismiss says so', async () => {
    const counted = await page(circle({ countdown: 1, config: settings({ countdown: { duration: '300ms', action: 'default' } }) }) + '<script type="text/plain" data-consent-script="ANALYTICS"></script>');
    assert.equal(counted.Consent.undecided(), true);
    await counted.after(700);
    assert.equal(counted.Consent.undecided(), false);
    assert.equal(counted.Consent.state('USER'), true);
    assert.equal(counted.Consent.state('ANALYTICS'), false, 'a tracker never starts by itself');
    assert.equal(counted.$('#ga-cookie-footer').hidden, true);

    const waits = await page(circle() + '<main id="elsewhere"></main>');
    waits.click('#elsewhere');
    assert.equal(waits.Consent.undecided(), true, 'dismiss: none, the default - the question stays');
    assert.equal(waits.$('#ga-cookie-footer').hidden, false);

    const elsewhere = await page(circle({ config: settings({ dismiss: 'default' }) }) + '<main id="elsewhere"></main>');
    elsewhere.click('#elsewhere');
    assert.equal(elsewhere.Consent.undecided(), false, 'dismiss: default - a click elsewhere keeps the defaults');
    assert.equal(elsewhere.Consent.state('USER'), true);
});

test('a click on Symfony\'s web debug toolbar, or what consent.ignore names, is not on the page', async () => {
    const toolbar = '<div class="sf-toolbar sf-toolbar-opened"><div id="sfwdtabc123"><button id="tool">a panel of the toolbar</button></div></div>'
        + '<button class="sf-toolbar-toggle-button" id="sfToolbarToggleButton-abc123">fold</button>'
        + '<aside data-consent-ignore><button id="mine">a tool of the site</button></aside><main id="elsewhere"></main>';

    // Floating, under a dismiss that answers: the toolbar answers nothing, and the question stays.
    const floating = await page(circle({ config: settings({ dismiss: 'default' }) }) + toolbar);
    floating.click('#tool');
    floating.click('#sfToolbarToggleButton-abc123');
    floating.click('#mine');
    assert.equal(floating.Consent.undecided(), true, 'nothing answered by a click on the toolbar');
    assert.equal(floating.$('#ga-cookie-footer').hidden, false, 'the question still floats');
    floating.click('#elsewhere');
    assert.equal(floating.Consent.undecided(), false, 'the page itself is still elsewhere');

    // Answered, the panel opened again: the toolbar leaves it open, the page closes it.
    const answered = await page(circle() + toolbar);
    answered.Consent.accept();
    answered.click('[data-consent-toggle]');
    assert.equal(answered.$('#ga-cookie-footer').hidden, false);
    answered.click('#tool');
    assert.equal(answered.$('#ga-cookie-footer').hidden, false, 'a click on the toolbar does not close it');
    answered.click('#elsewhere');
    assert.equal(answered.$('#ga-cookie-footer').hidden, true, 'a click elsewhere does');
});

test('another page\'s configuration (the navigation): its words, the same answer', async () => {
    const { window, document, Consent, click, $ } = await page('<div id="page">' + circle() + '</div>');
    Consent.accept();

    const other = settings();
    other.categories[1].label = 'Vos préférences';
    document.querySelector('#page').innerHTML = circle({ config: other });
    window.dispatchEvent(new window.Event('transparent:load'));

    assert.equal(Consent.undecided(), false);
    assert.equal($('[data-consent-details]').hidden, false);
    click('[data-consent-toggle]');
    assert.equal($('[data-consent-row="USER"] [data-consent-label]').textContent, 'Vos préférences');
    assert.equal($('#ga-cookie-footer').hidden, false);
});

// jsdom lays nothing out: the panel's box is given here - where its circle starts, plus its margins (the
// circle's own `left`), 288px wide, 340 in the detailed section -, the screen `screen.width` wide.
function laidOut(window, at, screen) {
    Object.defineProperty(window.document.documentElement, 'clientWidth', { configurable: true, get: () => screen.width });
    const native = window.HTMLElement.prototype.getBoundingClientRect;
    window.HTMLElement.prototype.getBoundingClientRect = function () {
        if (!this.classList.contains('ga-cookie-panel') || this.hidden) return native.call(this);
        const width = this.classList.contains('is-customizing') ? 340 : 288;
        const left = at[this.closest('.ga-cookie').id] + (parseFloat(this.style.marginLeft) || 0);
        return { left, right: left + width, width, top: 500, bottom: 700, height: 200, x: left, y: 500 };
    };
}
const box = (element) => element.getBoundingClientRect();

test('the panel stays inside the screen across, whichever circle opens it: 16px from each edge, its start in sight when too wide', async () => {
    const screen = { width: 390 };
    const { window, click, $, Cookie, errors } = await page(circle({ place: 'bar' }) + circle({ place: 'footer' }), {
        local: { 'cookie/USER': 'true' },
        before: (window) => laidOut(window, { 'circle-bar': 20, 'circle-footer': 200 }, screen)
    });

    click('#circle-footer [data-consent-toggle]');
    const panel = Cookie.banner();
    assert.equal(panel.parentNode, $('#circle-footer'));
    assert.equal(panel.classList.contains('is-customizing'), true, 'the detailed section, 340 wide: 200 + 340 is past 390');
    assert.equal(box(panel).right, 374, 'moved left, 16px from the right edge');
    assert.equal(panel.style.marginLeft, '-166px');
    assert.equal(panel.style.marginRight, '166px', 'by both margins: a panel the site anchors on the right moves the same way');

    click('#circle-footer [data-consent-toggle]');
    assert.equal(panel.hidden, true);
    assert.equal(panel.style.marginLeft + panel.style.marginRight, '', 'closed, where the site put it');

    click('#circle-bar [data-consent-toggle]');
    assert.equal(Cookie.banner().parentNode, $('#circle-bar'));
    assert.equal(box(panel).left, 20);
    assert.equal(panel.style.marginLeft, '', 'inside already: untouched');

    screen.width = 320;
    window.dispatchEvent(new window.Event('resize'));
    assert.equal(box(panel).left, 16, 'a narrower screen: too wide for it, its start stays in sight');
    assert.equal(panel.style.marginLeft, '-4px');
    screen.width = 1440;
    window.dispatchEvent(new window.Event('resize'));
    assert.equal(panel.style.marginLeft, '', 'and back where it was on a wide one');
    assert.deepEqual(errors, []);
});

test('a "Cookies" link far from the circle brings its panel on screen upwards or downwards, never sideways', async () => {
    const scrolled = [];
    const { window, click, $, Cookie } = await page('<div id="list" style="overflow-y: auto">' + circle() + '</div><a href="#" data-consent-open id="link">cookies</a>', {
        local: { 'cookie/USER': 'true' },
        before: (window) => {
            let inner = 0;
            const list = window.document.getElementById('list');
            Object.defineProperty(list, 'scrollTop', { configurable: true, get: () => inner, set: (v) => { inner = v; } });
            Object.defineProperty(list, 'scrollHeight', { configurable: true, get: () => 2000 });
            Object.defineProperty(list, 'clientHeight', { configurable: true, get: () => list.dataset.scrolls ? 300 : 2000 });
            list.getBoundingClientRect = () => ({ left: 0, right: 390, width: 390, top: 100, bottom: 400, height: 300, x: 0, y: 100 });
            const native = window.HTMLElement.prototype.getBoundingClientRect;
            window.HTMLElement.prototype.getBoundingClientRect = function () {
                if (!this.classList.contains('ga-cookie-panel') || this.hidden) return native.call(this);
                const top = 900 - inner;
                return { left: 500, right: 788, width: 288, top, bottom: top + 200, height: 200, x: 500, y: top };
            };
            Object.defineProperty(window, 'scrollX', { configurable: true, get: () => 40 });
            Object.defineProperty(window, 'scrollY', { configurable: true, get: () => 10 });
            window.scrollTo = (x, y) => scrolled.push([x, y]);
            window.Element.prototype.scrollIntoView = function () { scrolled.push('scrollIntoView'); };
        }
    });
    assert.equal(window.innerHeight, 768);

    click('#link');
    assert.equal(Cookie.banner().hidden, false);
    assert.deepEqual(scrolled, [[40, 342]], 'the page, by what the panel is below the screen (1100 - 768), the sideways scroll kept');

    click('[data-consent-close]');
    assert.equal(Cookie.banner().hidden, true);
    $('#list').dataset.scrolls = 'yes';
    click('#link');
    assert.equal($('#list').scrollTop, 700, 'a box that scrolls it first: its bottom on the box\'s (1100 - 400)');
    assert.deepEqual(scrolled, [[40, 342]], 'then in sight: the page stays');

    click('[data-consent-close]');
    const style = window.document.createElement('style');
    style.textContent = '.ga-cookie-panel { position: fixed; }';
    window.document.head.appendChild(style);
    $('#list').scrollTop = 0;
    click('#link');
    assert.equal(Cookie.banner().hidden, false);
    assert.equal($('#list').scrollTop, 0, 'fixed, on screen already: nothing scrolls');
    assert.deepEqual(scrolled, [[40, 342]]);
});

test('not one sentence in the script', async () => {
    const { readFileSync } = await import('node:fs');
    const source = readFileSync(new URL('../../public/js/consent.js', import.meta.url), 'utf8');
    const sentences = [];
    source.replace(/\/\*[\s\S]*?\*\//g, '').split('\n').forEach((line, n) => {
        const code = line.replace(/\s\/\/ .*$/, '').replace(/^\s*\/\/.*$/, '');
        (code.match(/'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*"/g) || []).forEach((literal) => {
            if (/[A-Za-z]{2,} [A-Za-z]{2,}/.test(literal) && !/[\[\]=]/.test(literal) && literal !== "'use strict'") sentences.push((n + 1) + ': ' + literal);
        });
    });
    assert.deepEqual(sentences, []);
});

test('countdown_action: refuse - when the time is up every feature is off but the required ones, the panel closes, the answer is kept', async () => {
    const counted = await page(circle({ countdown: 1, config: settings({ countdown: { duration: '300ms', action: 'refuse' } }) }) + '<script type="text/plain" data-consent-script="ANALYTICS"></script>');
    assert.equal(counted.$('#circle-footer').getAttribute('data-consent-action'), 'refuse');
    assert.equal(counted.Consent.undecided(), true);
    await counted.after(700);
    assert.equal(counted.Consent.undecided(), false, 'the countdown answered');
    assert.equal(counted.Consent.state('NECESSARY'), true);
    assert.equal(counted.Consent.state('USER'), false, 'an implicit feature refused too');
    assert.equal(counted.Consent.state('LOCATION'), false);
    assert.equal(counted.Consent.state('ANALYTICS'), false);
    assert.equal(counted.$('#ga-cookie-footer').hidden, true);
    assert.equal(counted.window.localStorage.getItem('cookie/USER'), 'false', 'kept where @glitchr/cookie kept it');
});
