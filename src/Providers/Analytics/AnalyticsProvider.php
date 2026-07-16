<?php

declare(strict_types=1);

namespace Arthouse\Providers\Analytics;

use Arthouse\Providers\SettingsHubProvider;
use IX\Providers\Provider;

/**
 * Shared Segment analytics + Segment Consent Manager modal for ARTHOUSE sites.
 *
 * assets/js/index.js bootstraps analytics.js, then @segment/consent-manager
 * gates destinations (GTM, GA4, Facebook Pixel, …) for EU/UK visitors via a
 * custom Accept / Reject / More Options modal. Visitors outside the EU (by
 * browser timezone) are auto-consented, so the US audience never sees the banner.
 *
 * Loads on staging + production only (see {@see shouldLoad()}), so local dev
 * never hits Segment.
 *
 * All config lives in the CMS (Site Settings → Analytics): the Segment write key
 * (a public client-side identifier — set entirely in the CMS; **blank disables
 * Segment + the consent modal**, fail-safe off) and the consent-modal copy, so
 * the client can edit it without a deploy. The Analytics tab is registered ad-hoc
 * onto the shared Settings hub with canonical `field_arthouse_analytics_*` keys,
 * identical across every site.
 *
 * Brand styling is not baked in: assets/scss/index.scss references semantic
 * `--consent-*` custom properties which each child theme maps to its own palette
 * tokens (see UPGRADING.md). A site can be used directly — the only per-site
 * variance is that CSS token map — or subclassed to override {@see textDomain()}.
 */
class AnalyticsProvider extends Provider
{
    private const CONSENT_MANAGER_URL = 'https://unpkg.com/@segment/consent-manager@5.3.0/standalone/consent-manager.js';

    /** Script/style handle + the localized JS global — canonical across all sites. */
    protected const HANDLE = 'arthouse-analytics';
    protected const JS_GLOBAL = 'arthouseAnalytics';

    /** Text domain for the (untranslated) tab label. */
    protected function textDomain(): string
    {
        return 'arthouse-kit';
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_footer', [$this, 'outputConsentModal']);
        add_action('acf/init', [$this, 'registerSettingsFields'], 10);

        parent::register();
    }

    /**
     * Add the "Analytics" tab (Segment + consent copy) to the shared Settings hub,
     * ad-hoc, with canonical field_arthouse_analytics_* keys.
     */
    public function registerSettingsFields(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }
        $parent = SettingsHubProvider::GROUP_KEY;
        $order  = SettingsHubProvider::ORDER_ANALYTICS;
        $domain = $this->textDomain();

        acf_add_local_field(['key' => 'field_arthouse_tab_analytics', 'parent' => $parent, 'label' => __('Analytics', $domain), 'name' => '', 'type' => 'tab', 'placement' => 'top', 'menu_order' => $order]);
        acf_add_local_field(['key' => 'field_arthouse_analytics_segment_write_key', 'parent' => $parent, 'menu_order' => $order + 1, 'label' => 'Segment Write Key', 'name' => 'segment_write_key', 'type' => 'text', 'instructions' => 'Source write key from your Segment workspace. Public client-side identifier — safe to ship in JS. Leave blank to disable Segment + the consent modal entirely.']);
        acf_add_local_field(['key' => 'field_arthouse_analytics_segment_cookie_domain', 'parent' => $parent, 'menu_order' => $order + 2, 'label' => 'Segment Cookie Domain', 'name' => 'segment_cookie_domain', 'type' => 'text', 'instructions' => 'Domain for the tracking-preferences cookie (e.g. "' . $this->cookieDomainPlaceholder() . '"). Leading dot includes subdomains. Leave blank to derive from the site URL.', 'placeholder' => $this->cookieDomainPlaceholder()]);
        acf_add_local_field(['key' => 'field_arthouse_analytics_consent_heading', 'parent' => $parent, 'menu_order' => $order + 3, 'label' => 'Consent — Heading', 'name' => 'consent_heading', 'type' => 'text', 'default_value' => 'Your privacy matters']);
        acf_add_local_field(['key' => 'field_arthouse_analytics_consent_body', 'parent' => $parent, 'menu_order' => $order + 4, 'label' => 'Consent — Body Copy', 'name' => 'consent_body', 'type' => 'textarea', 'rows' => 4, 'default_value' => 'We use cookies to understand how you use our site and to improve your experience. By clicking "Accept all", you consent to our use of cookies.']);
        acf_add_local_field(['key' => 'field_arthouse_analytics_consent_accept_label', 'parent' => $parent, 'menu_order' => $order + 5, 'label' => 'Consent — Accept Button Label', 'name' => 'consent_accept_label', 'type' => 'text', 'default_value' => 'Accept all']);
        acf_add_local_field(['key' => 'field_arthouse_analytics_consent_reject_label', 'parent' => $parent, 'menu_order' => $order + 6, 'label' => 'Consent — Reject Button Label', 'name' => 'consent_reject_label', 'type' => 'text', 'default_value' => 'Reject all']);
        acf_add_local_field(['key' => 'field_arthouse_analytics_consent_options_label', 'parent' => $parent, 'menu_order' => $order + 7, 'label' => 'Consent — More Options Link Label', 'name' => 'consent_options_label', 'type' => 'text', 'default_value' => 'More Options']);
    }

    public function enqueueAssets(): void
    {
        $writeKey = $this->getOption('segment_write_key');

        if (!$this->shouldLoad() || $writeKey === '') {
            return;
        }

        // Head, not footer — analytics.js should load as early as possible.
        $this->enqueueScript(self::HANDLE, 'index.js', [], false);

        wp_localize_script(self::HANDLE, self::JS_GLOBAL, [
            'writeKey' => $writeKey,
            'domain'   => $this->getOption('segment_cookie_domain') ?: parse_url(home_url(), PHP_URL_HOST),
        ]);

        wp_enqueue_script(
            'segment-consent-manager',
            self::CONSENT_MANAGER_URL,
            [self::HANDLE],
            null,
            ['strategy' => 'defer', 'in_footer' => false]
        );

        $this->enqueueStyle(self::HANDLE, 'analytics.css');
    }

    public function outputConsentModal(): void
    {
        if (!$this->shouldLoad() || $this->getOption('segment_write_key') === '') {
            return;
        }

        $heading      = $this->getOption('consent_heading', 'Your privacy matters');
        $body         = $this->getOption('consent_body', 'We use cookies to understand how you use our site and to improve your experience. By clicking "Accept all", you consent to our use of cookies.');
        $acceptLabel  = $this->getOption('consent_accept_label', 'Accept all');
        $rejectLabel  = $this->getOption('consent_reject_label', 'Reject all');
        $optionsLabel = $this->getOption('consent_options_label', 'More Options');
        ?>
<div id="consent-container-modal" class="consent-container-modal" hidden>
    <div class="consent-form">
        <div class="consent-heading"><?php echo esc_html($heading); ?></div>
        <p><?php echo esc_html($body); ?></p>
        <div class="consent-actions">
            <a id="consent-btn-reject" href="#" class="consent-button"><?php echo esc_html($rejectLabel); ?></a>
            <a id="consent-btn-accept" href="#" class="consent-button consent-button-primary"><?php echo esc_html($acceptLabel); ?></a>
        </div>
        <a id="consent-btn-options" href="#" class="consent-options-link"><?php echo esc_html($optionsLabel); ?></a>
    </div>
</div>
<div id="target-container" class="target-container"></div>
        <?php
    }

    /**
     * Load Segment on staging + production only. Protected so a site can widen it
     * (e.g. to smoke-test tracking on a local/dev environment).
     */
    protected function shouldLoad(): bool
    {
        return in_array(wp_get_environment_type(), ['production', 'staging'], true);
    }

    /**
     * A sensible per-site placeholder/example for the cookie-domain field, derived
     * from the site host (leading dot, www stripped). Purely a UI hint.
     */
    protected function cookieDomainPlaceholder(): string
    {
        $host = (string) parse_url(home_url(), PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host);

        return $host !== '' ? '.' . $host : '';
    }

    private function getOption(string $key, string $default = ''): string
    {
        if (!function_exists('get_field')) {
            return $default;
        }
        $value = get_field($key, 'option');

        return ($value !== '' && $value !== null && $value !== false) ? (string) $value : $default;
    }
}
