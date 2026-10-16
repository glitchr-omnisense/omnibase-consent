/*
 * omnibase/consent - the cookie answer of an omnibase site, feature by
 * feature: window.Consent, in front of @glitchr/flowjs/cookie (the Cookie
 * global omnibase loads on every page).
 *
 * The library does the work: it keeps the answer (one yes or no per
 * category, in a cookie, and in localStorage "cookie/<GROUP>" as
 * @glitchr/cookie did), asks again when it has to, counts down, shows the
 * question and the detailed section, erases what a refused category stores
 * and lets nothing be written under it, wakes the elements that wait. This
 * script gives it the site's configuration - the JSON the partial prints
 * (consent_settings(): the features as categories, their words, what each
 * stores) -, tells it which markup is its banner (the partial's panel,
 * attributes data-consent-*), and keeps what the sites were written against:
 *
 *   Consent.state(name)    true | false | null (not answered)
 *   Consent.enabled(name)  what a script should do now (an implicit feature is on until refused)
 *   Consent.use(name, options, onEnable, onDisable)
 *   Consent.cookie(group, name, value, expires)   Cookie.set() through the answer
 *   Consent.undecided()  Consent.refused()  Consent.accepted()   (the site's own feature)
 *   Consent.accept()  Consent.refuse()  Consent.open()  Consent.close()  Consent.features()  Consent.scan()
 *
 * A FEATURE is a category of the library under an upper-case name: the site's
 * own (USER), what it cannot work without (NECESSARY), the time zone
 * (LOCATION), a chat (CHAT), a measure of audience (ANALYTICS)... They come
 * from the configuration, from the scripts as they run
 *     Consent.use('CHAT', {label: 'Chat'}, () => loadCrisp(), () => hideCrisp());
 * and from any script writing a cookie through Cookie.set(group, ...): an
 * unknown group becomes a feature with its switch, off until the visitor
 * says yes. Elements wait for a feature too: <script type="text/plain"
 * data-consent-script="ANALYTICS" data-src="..."> runs, and
 * <iframe data-consent-script="SOCIAL" data-consent-src="..."> loads, once on.
 *
 * The circles (there may be several on a page) share one panel, the
 * library's banner: it sits under the circle that shows it - the first the
 * visitor can see while the question floats, the one clicked afterwards.
 * The circle carries the states the stylesheets are written against:
 * is-floating (not answered: the panel at the bottom of the screen),
 * is-choosing (the detailed section), is-refused.
 *
 * Every answer dispatches `consent:change` on window ({detail: {consent,
 * groups, features}}), `ga:cookie` for the sites written before this bundle,
 * and, for each feature turned on, `<name>:consent` on the document (the
 * social bundle loads Instagram on `social:consent`) - sent again for every
 * feature already on, at load and after each page swap.
 *
 * The library comes with the page's own scripts, after this one: until they
 * have all run (DOMContentLoaded) the calls are kept, and answered from what
 * the browser already holds. A page that still packs @glitchr/cookie has no
 * such library: consent-legacy.js, the panel as it was, takes over there.
 *
 * Not one word the visitor reads is written here.
 */
(function () {
    'use strict';
    if (window.Consent) return;

    var WIDGET = '[data-consent=""]';
    var PANEL = '.ga-cookie-panel';
    var OPENERS = '[data-consent-open], [data-cookie-open]';
    var LEGACY = 'cookie/';

    // The library's attributes are the partial's, under this prefix; these are
    // the ones the partial already had another use for, or names differently.
    var PREFIX = 'data-consent';
    var HOOKS = {
        details: 'data-consent-section',      // the detailed section ([data-consent-details] is the button that opens it)
        customize: 'data-consent-details',
        toggle: 'data-consent-switch',        // a category's switch ([data-consent-toggle] is the circle)
        save: 'data-consent-keep',
        features: 'data-consent-governs',
        countdown: 'data-consent-counting',   // (data-consent-countdown: the seconds, on the circle)
        category: 'data-consent-script'
    };
    var CLASSES = { root: 'ga-cookie-panel', entry: 'ga-cookie-entry' };

    var Module = null;      // @glitchr/flowjs/cookie
    var started = false;
    var settings = null;    // the page's configuration, as the library takes it
    var text = null;        // as printed: read again only when another page prints another
    var own = { primary: 'USER', inherits: {}, durations: {} };   // what is this script's in it
    var queue = [];         // the calls made before the library was there
    var target = null;      // the circle whose panel the library takes when it needs one
    var spare = null;       // what it is given on a page without a circle
    var before = { open: false, pending: false };
    var scheduled = false;

    function upper(name) { return String(name === undefined || name === null ? '' : name).trim().toUpperCase(); }
    function capital(name) { return name.charAt(0) + name.slice(1).toLowerCase(); }
    function primary() { return upper(own.primary) || 'USER'; }

    function kept(name) {
        try {
            var value = window.localStorage.getItem(LEGACY + name);
            return value === null || value === undefined ? null : value === 'true';
        } catch (e) { return null; }
    }

    /** The configuration the page prints (the first circle's); false when it is the one already read. */
    function read() {
        var block = document.querySelector('script[type="application/json"][data-consent-settings]');
        if (!block || block.textContent === text) return false;
        var parsed;
        try { parsed = JSON.parse(block.textContent); } catch (e) { return false; }
        if (!parsed || typeof parsed !== 'object') return false;
        text = block.textContent;
        own = Object.assign({ primary: 'USER', inherits: {}, durations: {} }, parsed.consent || {});
        delete parsed.consent;
        settings = parsed;
        return true;
    }

    function configured(name) {
        var list = settings && Array.isArray(settings.categories) ? settings.categories : [];
        for (var i = 0; i < list.length; i++) if (upper(list[i].key) === name) return list[i];
        return null;
    }

    // ── The circles and the panel ───────────────────────────────────

    function circles() { return Array.prototype.slice.call(document.querySelectorAll(WIDGET)); }
    function panel(widget) { return widget ? widget.querySelector(PANEL) : null; }
    function button(widget) { return widget ? widget.querySelector('[data-consent-toggle]') : null; }

    /** On screen for the visitor: not in a hidden footer, a folded bar (display, visibility, opacity). */
    function visible(element) {
        if (!element || !element.isConnected) return false;
        if (typeof element.checkVisibility === 'function') return element.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true });
        return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    }

    /** The circle that shows the panel by itself: the first the visitor can see among those allowed to float, else the first. */
    function front() {
        var all = circles();
        var allowed = all.filter(function (widget) { return widget.getAttribute('data-consent-floating') !== 'false'; });
        return allowed.filter(visible)[0] || all.filter(visible)[0] || allowed[0] || all[0] || null;
    }

    function banner() {
        var root = Module ? Module.banner() : null;
        return root && root.isConnected ? root : null;
    }

    /** The library's banner: the panel of the circle it is to show under; on a page without a circle, nothing to see. */
    function template() {
        var widget = target && target.isConnected ? target : front();
        var found = panel(widget);
        if (found) return found;
        spare = document.createElement('div');
        return spare;
    }

    /** Put the one live panel under this circle; the panel that was there takes its place. */
    function seat(widget) {
        if (!widget) return;
        target = widget;
        var root = banner();
        var mine = panel(widget);
        if (!root || mine === root) return;
        if (mine) {
            var id = mine.id;
            mine.hidden = true;
            root.parentNode.insertBefore(mine, root);
            mine.id = root.id;
            root.id = id;
        }
        widget.appendChild(root);
    }

    /** The circles as the answer and the panel stand. */
    function paint() {
        var root = banner();
        var open = !!root && !root.hidden;
        var pending = Module.pending();
        var refused = Module.state(primary()) === false;
        circles().forEach(function (widget) {
            var mine = open && widget.contains(root);
            widget.classList.toggle('is-floating', mine && pending);
            widget.classList.toggle('is-choosing', mine && root.classList.contains('is-customizing'));
            widget.classList.toggle('is-refused', refused);
            var b = button(widget);
            if (!b) return;
            b.setAttribute('aria-expanded', mine ? 'true' : 'false');
            b.title = b.getAttribute(refused ? 'data-refused-label' : 'data-accepted-label') || '';
            if (mine && root.id) b.setAttribute('aria-controls', root.id);
        });
    }

    /** Opened under its circle (not floating), the panel is brought on screen: a "Cookies" link may be far from it. */
    function reveal() {
        var root = banner();
        if (!root || root.hidden || Module.pending() || typeof root.scrollIntoView !== 'function') return;
        root.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }

    /** A duration the library prints as configured ("30d") gets the words the page has for it. */
    function word() {
        var root = banner();
        if (!root) return;
        Array.prototype.forEach.call(root.querySelectorAll('[' + PREFIX + '-entry-duration]'), function (el) {
            var said = own.durations[el.textContent];
            if (said && said !== el.textContent) el.textContent = said;
        });
    }

    // ── The configuration ───────────────────────────────────────────

    /**
     * A feature split from another takes the answer the other was given: the
     * visitor who answered before the split is not asked again. Through the
     * keys @glitchr/cookie kept, which the library takes over.
     */
    function inheritKept() {
        if (!settings || settings.legacy === false) return;
        Object.keys(own.inherits).forEach(function (name) {
            var from = kept(upper(own.inherits[name]));
            if (from === null || kept(upper(name)) !== null) return;
            try { window.localStorage.setItem(LEGACY + upper(name), String(from)); } catch (e) { /* closed: asked again */ }
        });
    }

    /** The same, for an answer the library holds without those keys. */
    function inherit() {
        var map = {};
        var some = false;
        Object.keys(own.inherits).forEach(function (name) {
            var from = Module.state(own.inherits[name]);
            if (from === null || !Module.category(name) || Module.state(name) !== null) return;
            map[upper(name)] = from;
            some = true;
        });
        if (some) Module.save(map);
    }

    function configure() {
        inheritKept();
        Module.ready(Object.assign({}, settings, { prefix: PREFIX, hooks: HOOKS, classes: CLASSES, observe: true, template: template }));
        inherit();
    }

    /** The page as it is now: its configuration if it is another one, its circles, the panel where it belongs. */
    function adopt() {
        if (read()) configure();
        var all = circles();
        // A panel that left with its page (Turbo, a footer rebuilt), or the stand-in of a page without a circle: the library takes this page's.
        var root = Module.banner();
        if (root && (!root.isConnected || (root === spare && all.length))) {
            if (root === spare && spare.parentNode) spare.parentNode.removeChild(spare);
            spare = null;
            Module.ready({});
        }
        all.forEach(function (widget) {
            // "Personnaliser" is always there: hidden as printed, for the pages consent-legacy.js serves.
            Array.prototype.forEach.call(widget.querySelectorAll('[' + HOOKS.customize + '][hidden]'), function (el) { el.hidden = false; });
        });
        word();
        paint();
    }

    function later() {
        if (scheduled) return;
        scheduled = true;
        var run = function () { scheduled = false; adopt(); };
        if (typeof window.requestAnimationFrame === 'function') window.requestAnimationFrame(run); else window.setTimeout(run, 16);
    }

    // ── For scripts ─────────────────────────────────────────────────

    /** Whether a cookie "GROUP/NAME" is declared under a category of its own. */
    function declared(name) {
        return Module.categories().some(function (category) {
            return category.storage.some(function (entry) {
                if (entry.type !== 'cookie' || typeof entry.name !== 'string') return false;
                if (entry.name.indexOf('*') === -1) return entry.name === name;
                return new RegExp('^' + entry.name.split('*').map(function (piece) { return piece.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('.*') + '$').test(name);
            });
        });
    }

    function use(name, options, onEnable, onDisable) {
        if (typeof options === 'function') { onDisable = onEnable; onEnable = options; options = {}; }
        name = upper(name);
        if (!name) return false;
        options = Object.assign({}, options || {});
        // The site's words win (the configuration's), then the script's, then the bare name.
        if (!options.label && !Module.category(name)) options.label = capital(name);
        return Module.use(name, options, onEnable, onDisable);
    }

    /** Cookie.set(), superseded: a group nobody declared becomes a feature, and nothing is written while it is off. */
    function supersede() {
        if (Module.__consent) return;
        var set = Module.set;
        Module.set = function (group, name) {
            var feature = upper(group);
            // Declared under a category of its own (USER/INFO), a cookie is that category's; else its group is a feature, listed from now on.
            if (feature && !declared(feature + '/' + upper(name))) use(feature);
            return set.apply(Module, arguments);
        };
        Module.__consent = true;
    }

    var live = {
        state: function (name) { return Module.state(upper(name) || primary()); },
        enabled: function (name) { return Module.granted(upper(name) || primary()); },
        use: use,
        cookie: function (group, name, value, expires) {
            var feature = upper(group);
            name = upper(name);
            // Cookie.set() is superseded: an unknown group is declared there, and nothing is written while its feature is off.
            Module.set(feature, name, value, expires);
            return Module.get(feature, name) !== null;
        },
        features: function () {
            return Module.categories().map(function (category) {
                return { name: upper(category.key), label: category.label, implicit: category.default, required: category.required, state: category.state, enabled: category.granted };
            });
        },
        undecided: function () { return Module.pending(); },
        refused: function () { return Module.state(primary()) === false; },
        accepted: function () { return Module.state(primary()) !== false; },
        accept: function () { Module.accept(); },
        refuse: function () { Module.refuse(); },
        open: function () {
            if (!banner() || banner().hidden) seat(front());
            Module.open();
        },
        close: function () { if (!Module.pending()) Module.close(); },
        scan: function (root) { adopt(); Module.scan(root); }
    };

    // Before the library is there: what the browser already holds answers, the rest waits.
    function guess(name, options) {
        name = upper(name) || primary();
        var category = configured(name);
        if (category && category.required) return true;
        var value = kept(name);
        if (value !== null) return value;
        if (category) return !!category.default;
        return !!(options && (options.implicit === true || options.default === true));
    }
    function wait(method) { return function () { queue.push([method, arguments]); }; }
    var early = {
        state: function (name) {
            name = upper(name) || primary();
            var category = configured(name);
            return category && category.required ? true : kept(name);
        },
        enabled: function (name) { return guess(name); },
        use: function (name, options) { queue.push(['use', arguments]); return guess(name, typeof options === 'function' ? null : options); },
        cookie: function (group) { queue.push(['cookie', arguments]); return guess(group); },
        features: function () {
            return (settings && Array.isArray(settings.categories) ? settings.categories : []).filter(function (category) { return !category.onDemand; }).map(function (category) {
                var name = upper(category.key);
                return { name: name, label: category.label, implicit: !!category.default, required: !!category.required, state: early.state(name), enabled: guess(name) };
            });
        },
        undecided: function () { return kept(primary()) === null; },
        refused: function () { return kept(primary()) === false; },
        accepted: function () { return kept(primary()) !== false; },
        accept: wait('accept'), refuse: wait('refuse'), open: wait('open'), close: wait('close'), scan: wait('scan')
    };

    var impl = early;
    var api = {};
    Object.keys(early).forEach(function (method) {
        api[method] = function () { return impl[method].apply(api, arguments); };
    });
    window.Consent = api;

    // ── With the library ────────────────────────────────────────────

    function names() {
        var all = {};
        (settings.categories || []).forEach(function (category) { all[upper(category.key)] = true; });
        Module.categories().forEach(function (category) { all[upper(category.key)] = true; });
        return Object.keys(all);
    }

    /** Each feature already on says so to the page's scripts: on the first load and after each page swap. */
    function announce() {
        names().forEach(function (name) {
            if (Module.granted(name)) document.dispatchEvent(new CustomEvent(name.toLowerCase() + ':consent'));
        });
    }

    function onAnswer(event) {
        var detail = event.detail || {};
        paint();
        (detail.granted || []).forEach(function (key) {
            document.dispatchEvent(new CustomEvent(String(key).toLowerCase() + ':consent'));
        });
        var features = {};
        Object.keys(detail.categories || {}).forEach(function (key) { features[upper(key)] = detail.categories[key]; });
        window.dispatchEvent(new CustomEvent('consent:change', { detail: { consent: Module.granted(primary()), groups: Object.keys(features), features: features } }));
        window.dispatchEvent(new Event('ga:cookie'));
    }

    /**
     * Whether a click there is not on the page: in what consent.ignore names (Symfony's web debug toolbar,
     * [data-consent-ignore]...). Asked of the library when it knows (@glitchr/flowjs 1.3.1, its ignore option).
     */
    function ignored(element) {
        if (typeof Module.ignored === 'function') return Module.ignored(element);
        var list = settings && settings.ignore;
        if (!list) return false;
        return (Array.isArray(list) ? list : [list]).some(function (selector) {
            try { return typeof selector === 'string' && !!element.closest(selector); } catch (e) { return false; }
        });
    }

    /** Before the library hears the click: how things stood, and the panel where the visitor can see it. */
    function onBefore(event) {
        var root = banner();
        before.open = !!root && !root.hidden;
        before.pending = Module.pending();
        var clicked = event.target instanceof Element ? event.target : null;
        if (clicked && !before.open && clicked.closest(OPENERS)) seat(front());
    }

    function onClick(event) {
        var clicked = event.target instanceof Element ? event.target : null;
        if (!clicked) return;
        // [data-consent-open] is the library's own; the attribute of its default prefix opens the panel too.
        if (clicked.closest('[data-consent-open]')) return;
        var opener = clicked.closest('[data-cookie-open]');
        if (opener) {
            event.preventDefault();
            Module.open(undefined, opener);
            return;
        }
        // "Tout accepter", "Tout refuser": the detailed section's own, beside the question's accept and refuse.
        var all = clicked.closest('[data-consent-all]');
        if (all) return void (all.getAttribute('data-consent-all') === 'refuse' ? Module.refuse() : Module.accept());
        var toggle = clicked.closest('[data-consent-toggle]');
        var widget = toggle ? toggle.closest(WIDGET) : null;
        if (widget) {
            // Floating, the library took this click for one elsewhere: left open (consent.dismiss none), or answered as it says.
            if (before.open && before.pending) return;
            var root = banner();
            if (before.open && root && widget.contains(root)) return void Module.close();
            seat(widget);
            Module.open(undefined, toggle);
            return;
        }
        // A click elsewhere closes the panel of a visitor who has answered - not one on what is not the page's.
        if (ignored(clicked)) return;
        var shown = banner();
        if (before.open && !before.pending && shown && !shown.contains(clicked)) Module.close();
    }

    function onKey(event) {
        if (event.key !== 'Escape') return;
        var root = banner();
        // The detailed section is the library's to close; the question of a visitor who has answered closes here.
        if (root && !root.hidden && !root.classList.contains('is-customizing') && !Module.pending()) Module.close();
    }

    function begin() {
        impl = live;
        supersede();
        window.addEventListener('cookie:consent', onAnswer);
        window.addEventListener('cookie:open', function () { word(); paint(); reveal(); });
        window.addEventListener('cookie:close', paint);
        document.addEventListener('click', onBefore, true);
        document.addEventListener('click', onClick);
        document.addEventListener('keydown', onKey);
        // The navigation fires on window, Turbo on the document (it bubbles to window: one listener each).
        window.addEventListener('transparent:load', function () { adopt(); Module.scan(); announce(); });
        document.addEventListener('turbo:load', function () { adopt(); Module.scan(); announce(); });

        text = null;
        adopt();
        if (typeof MutationObserver === 'function' && document.body) {
            new MutationObserver(function (mutations) {
                if (mutations.some(function (m) { return m.addedNodes.length || m.removedNodes.length; })) later();
            }).observe(document.body, { childList: true, subtree: true });
        }
    }

    /** Once the page's scripts have run: the library if it is there, the panel as it was otherwise; then the calls kept. */
    function start() {
        if (started) return;
        started = true;
        read();
        var found = (window.Flow && window.Flow.cookie) || (window.Cookie && window.Cookie.flow === true ? window.Cookie : null);
        if (found && typeof found.use === 'function' && settings) {
            Module = found;
            begin();
        } else if (typeof window.ConsentLegacy === 'function') {
            impl = window.ConsentLegacy();
        } else {
            return;
        }
        var calls = queue;
        queue = [];
        calls.forEach(function (call) {
            try { impl[call[0]].apply(api, call[1]); } catch (e) { if (window.console) console.error(e); }
        });
        if (Module) announce();
    }

    // Now, before the page's other scripts write anything: what a feature split from another inherits is known to them.
    if (read()) inheritKept();
    // A deferred script runs with the document "interactive", DOMContentLoaded still to come: only "complete" says
    // the page's scripts have run. On window, capturing: before the listeners those scripts put on the document,
    // which may call Consent at once. (`load` for a script added between the two events.)
    if (document.readyState === 'complete') {
        start();
    } else {
        window.addEventListener('DOMContentLoaded', start, true);
        window.addEventListener('load', start);
    }
})();
