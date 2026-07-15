<?php

declare(strict_types=1);

namespace Arthouse\Providers;

use IX\Providers\Provider;

/**
 * Shared SEO / head-metadata provider for ARTHOUSE marketing sites (raw mode).
 *
 * A child theme subclasses this and supplies at most two pieces of per-site
 * config — the brand theme-colour and (optionally) the text domain. Everything
 * else is shared and **canonical**: the SEO tab lands on the shared Settings hub
 * ({@see SettingsHubProvider::GROUP_KEY}) using canonical `field_arthouse_seo_*`
 * keys, identical across every site — no per-site prefix, no accommodation.
 *
 * All client-editable copy lives in the CMS (Site Settings → SEO): page title,
 * meta description, keywords, share image, and the raw JSON-LD structured data.
 * Nothing brand-specific is hardcoded (title falls back to the WP Site Title;
 * description/keywords are omitted when blank).
 *
 * The schema is whatever ARTHOUSE pastes into "Structured Data (JSON-LD)": it is
 * validated as JSON on save (a bad paste is rejected) and, on output, decoded and
 * re-encoded so only valid, safely-escaped JSON is emitted — a stray "</script>"
 * or malformed block can never break the page.
 */
class SeoProvider extends Provider
{
    /** Canonical ACF field-key prefix for every SEO field (shared across all sites). */
    private const KEY_PREFIX = 'field_arthouse_seo_';

    /** Brand colour for the theme-color meta tag. */
    protected function themeColor(): string
    {
        return '#000000';
    }

    /** Text domain for translatable strings (the SEO tab label + validation error). */
    protected function textDomain(): string
    {
        return 'arthouse-kit';
    }

    public function register(): void
    {
        add_filter('pre_get_document_title', [$this, 'filterTitle']);
        add_action('wp_head', [$this, 'outputMetaTags'], 2);
        add_action('wp_head', [$this, 'outputSchema'], 5);
        add_action('acf/init', [$this, 'registerSettingsFields'], 30);
        add_filter('acf/validate_value/name=schema_jsonld', [$this, 'validateSchemaJson'], 10, 4);

        parent::register();
    }

    /**
     * Add the "SEO" tab (title/description/keywords + share image + raw JSON-LD)
     * to the shared Settings hub, ad-hoc, with canonical keys.
     */
    public function registerSettingsFields(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }
        $parent = SettingsHubProvider::GROUP_KEY;
        $domain = $this->textDomain();
        $order  = SettingsHubProvider::ORDER_SEO;

        acf_add_local_field(['key' => $this->fieldKey('tab'), 'parent' => $parent, 'label' => __('SEO', $domain), 'name' => '', 'type' => 'tab', 'placement' => 'top', 'menu_order' => $order]);
        acf_add_local_field(['key' => $this->fieldKey('meta_title'), 'parent' => $parent, 'menu_order' => $order + 1, 'label' => 'Page Title', 'name' => 'meta_title', 'type' => 'text', 'instructions' => 'The browser-tab title and the Open Graph / Twitter title. Leave blank to use the site name.']);
        acf_add_local_field(['key' => $this->fieldKey('site_name'), 'parent' => $parent, 'menu_order' => $order + 2, 'label' => 'Site / Brand Name', 'name' => 'site_name', 'type' => 'text', 'instructions' => 'Brand name for og:site_name. Leave blank to use the WordPress Site Title.']);
        acf_add_local_field(['key' => $this->fieldKey('meta_description'), 'parent' => $parent, 'menu_order' => $order + 3, 'label' => 'Meta Description', 'name' => 'meta_description', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'Search-result + social-share description (~155 characters). Leave blank to omit it.']);
        acf_add_local_field(['key' => $this->fieldKey('keywords'), 'parent' => $parent, 'menu_order' => $order + 4, 'label' => 'Meta Keywords', 'name' => 'keywords', 'type' => 'text', 'instructions' => 'Comma-separated keywords (including cast names). Leave blank to omit.']);
        acf_add_local_field(['key' => $this->fieldKey('og_image'), 'parent' => $parent, 'menu_order' => $order + 5, 'label' => 'Share Image (Open Graph)', 'name' => 'og_image', 'type' => 'image', 'instructions' => 'Facebook / Twitter share image (1200×630). Used for og:image + twitter:image.', 'return_format' => 'url', 'preview_size' => 'medium', 'library' => 'all', 'mime_types' => 'jpg,jpeg,png']);
        acf_add_local_field(['key' => $this->fieldKey('schema_jsonld'), 'parent' => $parent, 'menu_order' => $order + 6, 'label' => 'Structured Data (JSON-LD)', 'name' => 'schema_jsonld', 'type' => 'textarea', 'rows' => 22, 'new_lines' => '', 'instructions' => 'Raw JSON-LD, output as an application/ld+json script on the homepage. Paste one complete, valid JSON object — it is validated on save (a bad paste is rejected). Leave blank to omit the schema.']);
    }

    public function filterTitle(string $title): string
    {
        return (is_front_page() || is_home()) ? $this->title() : $title;
    }

    public function outputMetaTags(): void
    {
        $canonical   = home_url('/');
        $title       = $this->title();
        $description = $this->getOption('meta_description');
        $keywords    = $this->getOption('keywords');
        $siteName    = $this->siteName();
        $ogImage     = $this->getOption('og_image');

        echo "\n<!-- ARTHOUSE SEO -->\n";
        printf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical));
        if ($description !== '') {
            printf('<meta name="description" content="%s" />' . "\n", esc_attr($description));
        }
        if ($keywords !== '') {
            printf('<meta name="keywords" content="%s" />' . "\n", esc_attr($keywords));
        }

        printf('<meta property="og:url" content="%s" />' . "\n", esc_url($canonical));
        printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($title));
        echo '<meta property="og:type" content="website" />' . "\n";
        printf('<meta property="og:site_name" content="%s" />' . "\n", esc_attr($siteName));
        if ($description !== '') {
            printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($description));
        }
        if ($ogImage !== '') {
            printf('<meta property="og:image" content="%s" />' . "\n", esc_url($ogImage));
        }

        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        printf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($title));
        if ($description !== '') {
            printf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr($description));
        }
        if ($ogImage !== '') {
            printf('<meta name="twitter:image" content="%s" />' . "\n", esc_url($ogImage));
        }

        printf('<meta name="theme-color" content="%s" />' . "\n", esc_attr($this->themeColor()));
    }

    /**
     * ACF save validation: a non-empty JSON-LD field must be valid JSON, so ARTHOUSE
     * can't save a block with a missing bracket/comma. Blank is allowed (omits schema).
     *
     * @param bool|string $valid
     * @param mixed       $value
     */
    public function validateSchemaJson($valid, $value, array $field, string $input)
    {
        if ($valid !== true) {
            return $valid;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return true;
        }
        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return sprintf(
                /* translators: %s: the JSON parser error message. */
                __('Invalid JSON — %s. Paste one complete JSON object.', $this->textDomain()),
                json_last_error_msg()
            );
        }
        return true;
    }

    /**
     * Emit the client-managed JSON-LD on the homepage. Decoded then re-encoded, so:
     * only valid JSON is ever output, and JSON_HEX_TAG neutralises any "</script>"
     * inside a string value — a bad or hostile block can't break out of the tag.
     */
    public function outputSchema(): void
    {
        if (!is_front_page()) {
            return;
        }
        $raw = trim($this->getOption('schema_jsonld'));
        if ($raw === '') {
            return;
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return;
        }
        $json = wp_json_encode($decoded, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        echo "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /**
     * The page title — the CMS "Page Title" if set, else the WordPress site name.
     * Never empty, so the document never ships a blank title.
     */
    protected function title(): string
    {
        return $this->getOption('meta_title') ?: (string) get_bloginfo('name');
    }

    /**
     * The brand name for og:site_name — the CMS "Site / Brand Name" if set, else the
     * WordPress site name.
     */
    protected function siteName(): string
    {
        return $this->getOption('site_name') ?: (string) get_bloginfo('name');
    }

    /** field_arthouse_seo_{suffix} — the canonical, cross-site ACF key scheme. */
    private function fieldKey(string $suffix): string
    {
        return self::KEY_PREFIX . $suffix;
    }

    /**
     * Read an ACF options-page field as a string ('' if ACF is inactive or the
     * field is empty). Image fields use return_format "url".
     */
    private function getOption(string $key): string
    {
        if (!function_exists('get_field')) {
            return '';
        }
        $value = get_field($key, 'option');

        return ($value !== null && $value !== false) ? (string) $value : '';
    }
}
