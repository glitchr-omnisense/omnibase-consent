// A page for the tests: the partial itself (templates/_cookie.html.twig), its
// Twig read just far enough to print it - the words are their keys in
// brackets -, the bundle's scripts, and @glitchr/flowjs/cookie when the page
// is one of a core that packs it.
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { JSDOM } from 'jsdom';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const file = (path) => readFileSync(join(root, path), 'utf8');
const scripts = { legacy: file('public/js/consent-legacy.js'), consent: file('public/js/consent.js') };
const library = ['core.js', 'cookie.js'].map((name) => file('node_modules/@glitchr/flowjs/src/js/' + name));
const twig = file('templates/_cookie.html.twig');

// The windows opened by a test, closed after it (a window that pretends to be visual keeps its frames
// coming): their observers let go first - a window that closes empties its document, and they would hear it.
const opened = [];
export async function closeAll() {
    await new Promise((resolve) => setTimeout(resolve, 40));
    opened.splice(0).forEach(({ window, observers }) => {
        observers.forEach((observer) => observer.disconnect());
        window.close();
    });
}

const attribute = (value) => String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

/** What consent_settings() prints for a site with these features (the bundle's own by default). */
export function settings(more = {}) {
    return {
        position: 'bottom-left', reopen: 'banner', dismiss: 'default',
        countdown: false,
        record: { lifetime: '180d' },
        signals: { gpc: false, dnt: false },
        categories: [
            { key: 'NECESSARY', label: 'What the site needs', description: 'Session and forms.', default: false, required: true, onDemand: false, storage: [{ type: 'cookie', name: 'PHPSESSID', duration: 'session', purpose: 'The session.' }] },
            { key: 'USER', label: 'Preferences', description: 'Language and theme.', default: true, required: false, onDemand: false, storage: [{ type: 'cookie', name: 'USER/INFO', duration: '30d', purpose: 'The browser.' }] },
            { key: 'LOCATION', label: 'Time zone', description: null, default: true, required: false, onDemand: false, storage: [{ type: 'cookie', name: 'LOCATION/TIMEZONE', duration: '30d', purpose: 'The time zone.' }] },
            { key: 'ANALYTICS', label: 'Audience', description: 'A count.', default: false, required: false, onDemand: true, storage: [{ type: 'cookie', name: 'ANALYTICS/VISITOR_ID', duration: '13mo', purpose: 'A random id.' }] }
        ],
        texts: { label: '[label]', accept: '[accept]', refuse: '[refuse]', customize: '[details]', detailsTitle: '[choices]', acceptAll: '[accept_all]', refuseAll: '[refuse_all]', save: '[save]', close: '[close]', required: '[required]', countdown: '[implicit {count}]', text: { html: '[text]' } },
        consent: { primary: 'USER', inherits: { LOCATION: 'USER' }, durations: { session: 'the visit', '30d': '30 days', '13mo': '13 months' } },
        ...more
    };
}

/** The partial, printed: {place, floating, countdown, settings, features} - features being what the circle hands consent-legacy.js. */
export function circle({ place = 'footer', floating = true, countdown = 0, features = [{ name: 'USER', implicit: true }], config = settings() } = {}) {
    let html = twig.replace(/\{#[\s\S]*?#\}/g, '').replace(/\{% set [^%]*%\}\n?/g, '');
    html = html.replace(/\{% if \(floating \?\? true\) is same as\(false\) %\}([^{]*)\{% endif %\}/, floating ? '' : '$1');
    html = html.replace(/\{%- if icon is defined -%\}[\s\S]*?\{%- else -%\}([\s\S]*?)\{%- endif -%\}/, '$1');
    html = html.replace(/\{% if _options\.countdown > 0 %\}([\s\S]*?)\{% endif %\}/, countdown > 0 ? '$1' : '');
    html = html.replace(/<link [^>]*>\n?/g, '').replace(/<script src=[^>]*><\/script>\n?/g, '');
    html = html.replace(/\{\{ ([\s\S]*?) \}\}/g, (all, expression) => {
        if (expression === '_id') return 'ga-cookie-' + place;
        if (expression === 'consent_settings()') return JSON.stringify(config).replace(/</g, '\\u003C');
        if (expression === '_options.countdown') return String(countdown);
        if (expression === '_options.groups|json_encode') return attribute(JSON.stringify(features.map((f) => f.name)));
        if (expression === '_options.features|json_encode') return attribute(JSON.stringify(features));
        if (expression === '_options.labels|json_encode') return attribute('{}');
        if (expression === '_options.erase|json_encode') return attribute('[]');
        if (expression.includes('implicit')) return '[implicit <span class="ga-cookie-count" data-consent-count>' + countdown + '</span>]';
        const key = /'@consent\.([a-z_]+)'\|trans/.exec(expression);
        if (key) return '[' + key[1] + ']';
        throw new Error('the test page does not know how to print {{ ' + expression + ' }}');
    });
    if (/\{[{%]/.test(html)) throw new Error('Twig left in the test page: ' + /\{[{%].{0,60}/.exec(html)[0]);
    return html.replace('class="ga-cookie"', 'class="ga-cookie" id="circle-' + place + '"');
}

/**
 * A page with this body, the scripts run as the browser does: consent.js
 * first (deferred, it comes before the core's), then the library if the page
 * has it and what `inline` does with it (the core's user.js), then
 * DOMContentLoaded.
 * {library: false} is a page that packs @glitchr/cookie: `Cookie` is then a stand-in with its setConsent().
 */
export async function page(body, { library: withLibrary = true, before = null, inline = null, cookies = [], local = {} } = {}) {
    const errors = [];
    const dom = new JSDOM('<!doctype html><html><body></body></html>', { runScripts: 'outside-only', url: 'https://example.test/', pretendToBeVisual: true });
    const { window } = dom;
    const { document } = window;
    const observers = [];
    const Native = window.MutationObserver;
    window.MutationObserver = class extends Native { constructor(heard) { super(heard); observers.push(this); } };
    opened.push({ window, observers });
    window.addEventListener('error', (event) => errors.push(event.error || event.message));
    cookies.forEach((cookie) => { document.cookie = cookie + '; path=/'; });
    Object.keys(local).forEach((key) => window.localStorage.setItem(key, local[key]));

    // jsdom's own document first (it loads after the constructor returns), then ours.
    if (document.readyState !== 'complete') await new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));

    // As while the document is still being read: the deferred scripts run, then DOMContentLoaded.
    let state = 'loading';
    Object.defineProperty(document, 'readyState', { configurable: true, get: () => state });
    document.body.innerHTML = body;
    if (before) before(window);
    window.eval(scripts.legacy);
    window.eval(scripts.consent);
    if (withLibrary) library.forEach((source) => window.eval(source));
    if (inline) inline(window);
    state = 'interactive';
    document.dispatchEvent(new window.Event('DOMContentLoaded', { bubbles: true }));
    state = 'complete';
    window.dispatchEvent(new window.Event('load'));

    const $ = (selector) => document.querySelector(selector);
    const click = (element) => (typeof element === 'string' ? $(element) : element).dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));
    const key = (name) => document.dispatchEvent(new window.KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true }));
    const has = (name) => document.cookie.split('; ').some((pair) => pair.split('=')[0] === name);
    const after = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    return { dom, window, document, Consent: window.Consent, Cookie: window.Cookie, errors, $, click, key, has, after };
}
