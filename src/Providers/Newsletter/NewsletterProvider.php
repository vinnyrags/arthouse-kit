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
 * CBA/AVFTB/MF. Owns the whole flow:
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
 * A site can use this class directly — the only required per-site variance is the
 * brand `--newsletter-*` token map in the child's stylesheet — or subclass it to
 * override the small config surface: {@see textDomain()}, {@see formId()},
 * {@see sendsNonce()}, {@see apiKeyFieldType()}, and the La MaMa second-list opt-in
 * ({@see laMamaListField()} / {@see optinLabel()} / {@see fineprint()}).
 *
 * MF keeps its own block markup + SCSS: it subclasses this for the PHP/endpoint/JS
 * but ships its own blocks/newsletter/, which overrides this one via the child-first
 * block search path (so its registered block stays matchbookfestival/newsletter).
 */
class NewsletterProvider extends Provider
{
    /** Filter render.php runs the block context through so a subclass can inject copy. */
    public const BLOCK_CONTEXT_FILTER = 'arthouse/newsletter/block_context';

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
        add_filter(self::BLOCK_CONTEXT_FILTER, [$this, 'filterBlockContext'], 10, 2);

        parent::register();
    }

    /**
     * Add the "Campaign Monitor" tab to the shared Settings hub, ad-hoc, with
     * canonical field_arthouse_newsletter_* keys. The La MaMa list field is only
     * registered when the site enables it ({@see laMamaListField()}).
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

        if ($this->laMamaListField() !== null) {
            acf_add_local_field(['key' => 'field_arthouse_newsletter_campaign_monitor_lamama_list_id', 'parent' => $parent, 'menu_order' => $order + 3, 'label' => 'Campaign Monitor List ID — La MaMa', 'name' => 'campaign_monitor_lamama_list_id', 'type' => 'text', 'instructions' => 'La MaMa\'s Campaign Monitor List ID. When the footer opt-in checkbox is ticked, the address is also subscribed to this list. Leave blank until it is provided — the checkbox is then captured but not routed.']);
        }
    }

    /**
     * Inject the La MaMa opt-in label + consent fineprint into the block context.
     *
     * The shared block renders those only when non-empty, so the base (no La MaMa)
     * emits just the form. Kept on a filter, not a block attribute, so the copy
     * lives in the subclass (code) — no per-instance ACF/DB migration.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function filterBlockContext(array $context, array $attributes): array
    {
        $context['optin_label'] = $this->optinLabel();
        $context['fineprint']   = $this->fineprint();

        return $context;
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

    /**
     * The ACF option name of the optional La MaMa second list, or null to disable.
     * When set, the field is registered on the hub and the endpoint routes a
     * best-effort second subscribe when the opt-in checkbox is ticked.
     */
    protected function laMamaListField(): ?string
    {
        return null;
    }

    /** Opt-in checkbox label. Non-empty → the block renders the La MaMa checkbox. */
    protected function optinLabel(): string
    {
        return '';
    }

    /** Consent fineprint under the form. Non-empty → the block renders it. */
    protected function fineprint(): string
    {
        return '';
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
