<?php

declare(strict_types=1);

namespace Arthouse\Providers\Newsletter;

use Arthouse\Providers\Newsletter\Endpoints\NewsletterSubscribeEndpoint;
use Arthouse\Providers\SettingsHubProvider;
use IX\Providers\Provider;

/**
 * Shared newsletter signup for ARTHOUSE sites — Campaign Monitor, AJAX, no redirect.
 *
 * Extracted from the (near-identical) per-site NewsletterProvider copies on
 * CBA/MF/AVFTB. Owns the whole base flow:
 *   - The arthouse/newsletter block (server-rendered BEM form).
 *   - The /wp-json/theme/v1/newsletter/subscribe REST endpoint
 *     ({@see NewsletterSubscribeEndpoint}), which subscribes to Campaign Monitor
 *     server-side via wp_remote_post — no createsend-php dependency.
 *   - The frontend handler (assets/js/newsletter.js) that fetch()es that endpoint
 *     and swaps the input to an inline status string; it NEVER navigates away.
 *   - The "Campaign Monitor" tab on the shared Settings hub, registered ad-hoc
 *     with canonical field_arthouse_newsletter_* keys (no ACF value migration).
 *
 * Credentials are CMS-only (Site Settings → Campaign Monitor); a blank API key or
 * list ID disables signups (fail-safe off) and skips shipping the frontend JS.
 *
 * The kit is deliberately generic — it carries no per-show concepts. The optional
 * partner opt-in (a second Campaign Monitor list) + consent fineprint are a generic,
 * CMS-driven capability: the label, the second list ID, and the fineprint copy are
 * ACF fields on the Campaign Monitor hub tab (registered here for every site), all
 * blank by default. A site turns the opt-in on purely by filling them in the CMS —
 * no per-show content or wiring in code. The block renders them (see render.php) and
 * the endpoint routes the best-effort second subscribe (see NewsletterSubscribeEndpoint).
 *
 * A site can use this class directly — the only required per-site variance is the
 * brand `--newsletter-*` token map — or subclass it to override the small config
 * surface: {@see textDomain()}, {@see formId()}, {@see sendsNonce()},
 * {@see apiKeyFieldType()}.
 *
 * MF keeps its own block markup + SCSS: it subclasses this for the PHP/endpoint/JS
 * but ships its own blocks/newsletter/, which overrides this one via the child-first
 * block search path (so its registered block stays matchbookfestival/newsletter).
 */
class NewsletterProvider extends Provider
{
    /**
     * The shared server-rendered signup block (blocks/newsletter/).
     *
     * @var string[]
     */
    protected array $blocks = ['newsletter'];

    /**
     * The Campaign Monitor subscribe endpoint. Pinned to the `theme/v1` namespace
     * (see $routeNamespace) so the public URL stays
     * /wp-json/theme/v1/newsletter/subscribe — what the frontend JS receives.
     *
     * @var array<class-string>
     */
    protected array $routes = [
        NewsletterSubscribeEndpoint::class,
    ];

    /** REST namespace override → theme/v1 (route version stays the default v1). */
    protected string $routeNamespace = 'theme';

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendScripts']);
        add_action('acf/init', [$this, 'registerSettingsFields'], 20);

        parent::register();
    }

    /**
     * Add the "Campaign Monitor" tab to the shared Settings hub, ad-hoc, with
     * canonical field_arthouse_newsletter_* keys.
     *
     * The primary API key + list ID drive signups. The optional partner opt-in
     * (label + second list) and consent fineprint are generic, CMS-driven, and blank
     * by default — a site enables the opt-in purely by filling them in, so no
     * per-show content lives in code.
     */
    public function registerSettingsFields(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }
        $parent = SettingsHubProvider::GROUP_KEY;
        $order  = SettingsHubProvider::ORDER_NEWSLETTER;
        $domain = $this->textDomain();

        acf_add_local_field(['key' => 'field_arthouse_tab_newsletter', 'parent' => $parent, 'label' => __('Campaign Monitor', $domain), 'name' => '', 'type' => 'tab', 'placement' => 'top', 'menu_order' => $order]);
        acf_add_local_field(['key' => 'field_arthouse_newsletter_campaign_monitor_api_key', 'parent' => $parent, 'menu_order' => $order + 1, 'label' => 'Campaign Monitor API Key', 'name' => 'campaign_monitor_api_key', 'type' => $this->apiKeyFieldType(), 'instructions' => 'Server-side API key for Campaign Monitor. Used to add subscribers via the /newsletter/subscribe REST endpoint. Leave blank to disable signups.']);
        acf_add_local_field(['key' => 'field_arthouse_newsletter_campaign_monitor_list_id', 'parent' => $parent, 'menu_order' => $order + 2, 'label' => 'Campaign Monitor List ID', 'name' => 'campaign_monitor_list_id', 'type' => 'text', 'instructions' => 'Hex ID of the list new subscribers are added to. Needed for signups to work.']);
        acf_add_local_field(['key' => 'field_arthouse_newsletter_optin_label', 'parent' => $parent, 'menu_order' => $order + 3, 'label' => 'Partner Opt-in — Checkbox Label', 'name' => 'campaign_monitor_optin_label', 'type' => 'text', 'instructions' => 'Optional. Shows a second opt-in checkbox above the fineprint (e.g. a co-marketing partner). Leave blank to hide the opt-in entirely.']);
        acf_add_local_field(['key' => 'field_arthouse_newsletter_optin_list_id', 'parent' => $parent, 'menu_order' => $order + 4, 'label' => 'Partner Opt-in — Campaign Monitor List ID', 'name' => 'campaign_monitor_optin_list_id', 'type' => 'text', 'instructions' => 'The Campaign Monitor List ID subscribed to when the opt-in box is ticked. Leave blank to capture the tick without routing it anywhere yet.']);
        acf_add_local_field(['key' => 'field_arthouse_newsletter_fineprint', 'parent' => $parent, 'menu_order' => $order + 5, 'label' => 'Consent Fineprint', 'name' => 'newsletter_fineprint', 'type' => 'textarea', 'rows' => 2, 'instructions' => 'Optional consent copy shown under the form (e.g. "By signing up, you agree to receive marketing emails."). Leave blank to hide.']);
    }

    /**
     * Frontend + editor block stylesheet. Called by BlockManager on
     * 'enqueue_block_assets' (both contexts); the editor sheet layers on in admin.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueDistStyle('arthouse-newsletter-block', 'css/newsletter.css');

        if (is_admin()) {
            $this->enqueueDistStyle('arthouse-newsletter-block-editor', 'css/newsletter-editor.css');
        }
    }

    /**
     * Editor script for the block. Called by BlockManager on
     * 'enqueue_block_editor_assets'. Depends on wp-server-side-render for the
     * ServerSideRender preview in edit.js.
     */
    public function enqueueBlockEditorAssets(): void
    {
        $this->enqueueEditorScript('arthouse-newsletter-block', 'newsletter.js', [
            'wp-server-side-render',
        ]);
    }

    /**
     * Enqueue the frontend form handler and localize its config. Skips when the
     * form can't work (no Campaign Monitor credentials) so no dead JS is shipped.
     *
     * The localized global is canonical (arthouseNewsletter); formId + sendsNonce
     * drive the shared handler so MF (id="subForm", nonce-less anonymous POST) and
     * the BEM sites (id="newsletterForm", X-WP-Nonce) share one script.
     */
    public function enqueueFrontendScripts(): void
    {
        if ($this->getOption('campaign_monitor_api_key') === '' || $this->getOption('campaign_monitor_list_id') === '') {
            return;
        }

        $this->enqueueScript('arthouse-newsletter', 'newsletter.js', [], true);

        wp_localize_script('arthouse-newsletter', 'arthouseNewsletter', [
            'endpoint' => esc_url_raw(rest_url('theme/v1/newsletter/subscribe')),
            'formId'   => $this->formId(),
            'nonce'    => $this->sendsNonce() ? wp_create_nonce('wp_rest') : null,
        ]);
    }

    /* -------------------------------------------------------------------------
     * Config surface — override in a thin per-site subclass.
     * ---------------------------------------------------------------------- */

    /** Text domain for the (untranslated) tab label. */
    protected function textDomain(): string
    {
        return 'arthouse-kit';
    }

    /** DOM id of the signup <form> the handler binds to. */
    protected function formId(): string
    {
        return 'newsletterForm';
    }

    /**
     * Whether the frontend POST carries a wp_rest nonce. Sites whose form can be
     * submitted by logged-in admins with a page-cached (stale) nonce should return
     * false — the handler then sends the request anonymously (credentials: 'omit')
     * to dodge a 403 from WP's cookie/nonce check (the endpoint is public anyway).
     */
    protected function sendsNonce(): bool
    {
        return true;
    }

    /** ACF field type for the API-key input ('text' or 'password'). Admin-UI only. */
    protected function apiKeyFieldType(): string
    {
        return 'text';
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
