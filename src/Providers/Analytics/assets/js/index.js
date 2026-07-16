// Segment + Consent Manager bootstrap (ARTHOUSE standard).
// `arthouseAnalytics.writeKey` and `.domain` are localized by AnalyticsProvider.

(function () {
    const config = window.arthouseAnalytics || {};
    const siteKey = config.writeKey;
    const domain = config.domain || window.location.hostname;

    if (!siteKey) {
        console.warn('[analytics] Missing Segment write key.');
        return;
    }

    // ---------- Segment snippet ----------
    !(function () {
        var i = 'analytics';
        var analytics = (window[i] = window[i] || []);
        if (analytics.initialize) return;
        if (analytics.invoked) {
            window.console && console.error && console.error('Segment snippet included twice.');
            return;
        }
        analytics.invoked = true;
        analytics.methods = ['trackSubmit','trackClick','trackLink','trackForm','pageview','identify','reset','group','track','ready','alias','debug','page','screen','once','off','on','addSourceMiddleware','addIntegrationMiddleware','setAnonymousId','addDestinationMiddleware','register'];
        analytics.factory = function (e) {
            return function () {
                if (window[i].initialized) return window[i][e].apply(window[i], arguments);
                var n = Array.prototype.slice.call(arguments);
                if (['track','screen','alias','group','page','identify'].indexOf(e) > -1) {
                    var c = document.querySelector("link[rel='canonical']");
                    n.push({ __t: 'bpc', c: (c && c.getAttribute('href')) || undefined, p: location.pathname, u: location.href, s: location.search, t: document.title, r: document.referrer });
                }
                n.unshift(e);
                analytics.push(n);
                return analytics;
            };
        };
        for (var n = 0; n < analytics.methods.length; n++) {
            var key = analytics.methods[n];
            analytics[key] = analytics.factory(key);
        }
        analytics.load = function (key, n) {
            var t = document.createElement('script');
            t.type = 'text/javascript';
            t.async = true;
            t.setAttribute('data-global-segment-analytics-key', i);
            t.src = 'https://cdn.segment.com/analytics.js/v1/' + key + '/analytics.min.js';
            var r = document.getElementsByTagName('script')[0];
            r.parentNode.insertBefore(t, r);
            analytics._loadOptions = n;
        };
        analytics._writeKey = siteKey;
        analytics.SNIPPET_VERSION = '5.2.0';
        analytics.load(siteKey);
        analytics.page();
    })();

    // ---------- Cookie helpers ----------
    function setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 86400000).toUTCString();
        document.cookie = `${name}=${value}; expires=${expires}; path=/; domain=.${domain}`;
    }
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()[\]\\\/+^]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }
    function stripCookiePrefix(value) {
        if (!value) return '';
        const first = value.indexOf('.');
        if (first < 0) return value;
        const second = value.indexOf('.', first + 1);
        return second < 0 ? value : value.substring(second + 1);
    }

    // ---------- Consent Manager config ----------
    window.consentManagerConfig = function (exports) {
        const inEU = exports.inEU;
        const categories = {
            Marketing: {
                title: 'Marketing',
                integrations: ['Google Analytics 4 Web', 'Indicative', 'Iterable', 'Hotjar'],
                purpose: 'To understand user behavior in order to provide you with a more relevant browsing experience or personalize the content on our site.',
            },
            Advertising: {
                title: 'Advertising',
                integrations: ['Google Tag Manager', 'Facebook Pixel', 'Personas Facebook Custom Audiences'],
                purpose: 'To personalize and measure the effectiveness of advertising on our site and other websites.',
            },
        };
        const cancelDialogContent = 'Your preferences have not been saved. By continuing to use our website, you’re agreeing to our Website Data Collection Policy.';

        const currentCookieValue = getCookie('tracking-preferences');
        const inEUTimezone = inEU.isInEUTimezone();

        if (inEUTimezone && !currentCookieValue) {
            const rejectBtn = document.getElementById('consent-btn-reject');
            const acceptBtn = document.getElementById('consent-btn-accept');
            const optionsBtn = document.getElementById('consent-btn-options');
            const modal = document.getElementById('consent-container-modal');

            if (rejectBtn) {
                rejectBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.consentManager.preferences.savePreferences({
                        destinationPreferences: { 'Google Tag Manager': false, Indicative: false, 'Google Analytics 4 Web': false },
                        customPreferences: { Marketing: false, Advertising: false, Essential: true },
                    });
                    if (modal) modal.hidden = true;
                    location.reload();
                });
            }
            if (acceptBtn) {
                acceptBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const cookieAllValue = '{%22version%22:1%2C%22destinations%22:{%22Google%20Analytics%204%20Web%22:true%2C%22Indicative%22:true}%2C%22custom%22:{%22Marketing%22:true%2C%22Advertising%22:true%2C%22Essential%22:true}}';
                    setCookie('tracking-preferences', cookieAllValue, 1000);
                    window.consentManager.preferences.savePreferences({
                        destinationPreferences: { 'Google Tag Manager': true, Indicative: true, 'Google Analytics 4 Web': true },
                        customPreferences: { Marketing: true, Advertising: true, Essential: true },
                    });
                    if (modal) modal.hidden = true;
                    location.reload();
                });
            }
            if (optionsBtn) {
                optionsBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.consentManager.openConsentManager();
                    window.consentManager.preferences.onPreferencesSaved(() => location.reload());
                });
            }
            if (modal) modal.hidden = false;
        }

        if (currentCookieValue && window.analytics) {
            try {
                const tracking = JSON.parse(decodeURIComponent(currentCookieValue));
                const advertising = tracking?.custom?.Advertising;
                const marketing = tracking?.custom?.Marketing;
                const adFlag = advertising === true ? 'granted' : 'denied';
                window.analytics.page('Consent Update', {
                    ad_personalization: adFlag,
                    ad_storage: adFlag,
                    ad_user_data: adFlag,
                    analytics_storage: marketing === true ? 'granted' : 'denied',
                    ga: stripCookiePrefix(getCookie('_ga')) ?? '',
                });
            } catch (err) {
                console.warn('[analytics] tracking-preferences cookie malformed:', err);
            }
        }

        if (!inEUTimezone && window.analytics) {
            window.analytics.page('Consent Update', {
                ad_personalization: 'granted',
                ad_storage: 'granted',
                ad_user_data: 'granted',
                analytics_storage: 'granted',
                ga: stripCookiePrefix(getCookie('_ga')) ?? '',
            });
        }

        return {
            container: '#target-container',
            writeKey: siteKey,
            shouldRequireConsent: inEU,
            bannerContent: 'Your Preferences',
            preferencesDialogTitle: 'Your Preferences',
            preferencesDialogContent: 'We track your data to improve your experience',
            cancelDialogContent: cancelDialogContent,
            customCategories: categories,
        };
    };
})();
