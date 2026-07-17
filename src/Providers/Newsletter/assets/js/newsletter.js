// Shared newsletter signup handler for ARTHOUSE sites (Campaign Monitor, AJAX).
//
// Intercepts the signup form submit, POSTs the email to the WP REST endpoint
// (which subscribes to Campaign Monitor server-side), and swaps the input's value
// to an inline status string. Crucially it calls preventDefault(), so the form
// NEVER does a native POST to createsend.com — no redirect.
//
// Config injected via wp_localize_script as window.arthouseNewsletter:
//   { endpoint: ".../wp-json/theme/v1/newsletter/subscribe",
//     formId:   "newsletterForm" | "subForm",
//     nonce:    "<wp_rest nonce>" | null }
//
// Behaviour is config-driven so one script serves every site:
//   - formId picks the form (#fieldEmail is the email input in all designs).
//   - When nonce is present it's sent as X-WP-Nonce. When it's null the request is
//     sent with credentials: 'omit' (no cookie) so a logged-in admin with a stale
//     page-cached nonce isn't 403'd by WP's cookie check before our handler runs.
//   - Any additional named control on the form (e.g. a CMS-driven partner opt-in
//     checkbox) is included in the POST body automatically — the kit doesn't need
//     to know field names.

(function () {
    var config = window.arthouseNewsletter;
    if (!config || !config.endpoint) {
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById(config.formId || 'newsletterForm');
        if (!form) {
            return;
        }

        var input = form.querySelector('#fieldEmail');
        if (!input) {
            return;
        }

        var emailRegex = /^([\w!#$%&'*+\-/=?^`{|}~]+\.)*[\w!#$%&'*+\-/=?^`{|}~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(:\d{1,5})?)$/i;

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var email = (input.value || '').trim();
            if (!emailRegex.test(email)) {
                input.value = 'invalid email address';
                return;
            }

            // email plus any other named control the site's form adds (opt-in, etc.)
            var body = { email: email };
            form.querySelectorAll('[name]').forEach(function (el) {
                if (!el.name || el.name === 'email') {
                    return;
                }
                body[el.name] = el.type === 'checkbox' ? el.checked : el.value;
            });

            var options = {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            };

            if (config.nonce) {
                options.headers['X-WP-Nonce'] = config.nonce;
            } else {
                options.credentials = 'omit';
            }

            fetch(config.endpoint, options)
                .then(function (response) {
                    return response.json().catch(function () {
                        return { ok: false };
                    });
                })
                .then(function (data) {
                    input.value = data && data.ok === true ? 'thank you!' : 'sorry, please try again.';
                })
                .catch(function () {
                    input.value = 'sorry, please try again.';
                });
        });
    });
})();
