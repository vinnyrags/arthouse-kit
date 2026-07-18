<?php

declare(strict_types=1);

namespace Arthouse\Providers\Sync;

use Arthouse\Providers\SettingsHubProvider;
use IX\Providers\Provider;
use Mythus\Support\Sync\SyncCommand;
use Mythus\Support\Sync\SyncCommandBuilder;
use Mythus\Support\Sync\SyncEnv;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The "Migration" tab on the shared Settings hub — env-aware one-way pull buttons
 * + an activity log — that drives the Mythus sync engine
 * ({@see \Mythus\Support\Sync\SyncCommand}).
 *
 * The shared hub page + group are owned by {@see SettingsHubProvider} (canonical
 * arthouse-site-settings / group_arthouse_site_settings). This provider only adds
 * its Migration tab to that group ad-hoc, like the Analytics / Campaign Monitor
 * providers, with canonical field_arthouse_migration_* keys — Sync is now a shared
 * platform capability, so its fields are canonicalised (unlike the AVFTB-native
 * original, whose keys were field_avftb_*).
 *
 * The tab only renders where a sync is possible (local + staging); production has
 * no allowed sources, so the tab is absent there. A site enables the feature purely
 * by configuring the mythus/sync/sources filter — no per-site code lives here.
 */
class SyncSettingsProvider extends Provider
{
    private const REST_NS    = 'theme/v1';
    private const REST_ROUTE = '/sync/pull';
    private const UI_FIELD   = 'field_arthouse_migration_ui';

    public function register(): void
    {
        // Priority 6 so the hub group (registered by SettingsHubProvider at 5)
        // exists before this tab is appended.
        add_action('acf/init', [$this, 'registerMigrationFields'], 6);
        add_filter('acf/load_field/key=' . self::UI_FIELD, [$this, 'injectMigrationUi']);
        add_action('admin_footer', [$this, 'printScript']);
        add_action('rest_api_init', [$this, 'registerRoutes']);

        parent::register();
    }

    public function registerMigrationFields(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }
        // Migration tab only where a sync is possible (local + staging); never on production.
        if (SyncEnv::allowedSources() === []) {
            return;
        }
        $parent = SettingsHubProvider::GROUP_KEY;
        $order  = SettingsHubProvider::ORDER_MIGRATION;
        $domain = $this->textDomain();

        acf_add_local_field(['key' => 'field_arthouse_tab_migration', 'parent' => $parent, 'label' => __('Migration', $domain), 'name' => '', 'type' => 'tab', 'placement' => 'top', 'menu_order' => $order]);
        acf_add_local_field(['key' => self::UI_FIELD, 'parent' => $parent, 'label' => __('Environment sync', $domain), 'name' => 'arthouse_migration_ui', 'type' => 'message', 'message' => '', 'new_lines' => '', 'esc_html' => 0, 'menu_order' => $order + 1]);
    }

    public function injectMigrationUi(array $field): array
    {
        $field['message'] = $this->migrationHtml();

        return $field;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::REST_NS, self::REST_ROUTE, [
            'methods'             => 'POST',
            'permission_callback' => static fn (): bool => current_user_can('manage_options'),
            'args'                => [
                'source' => ['required' => true, 'type' => 'string'],
                'force'  => ['type' => 'boolean', 'default' => false],
            ],
            'callback'            => [$this, 'handlePull'],
        ]);
    }

    public function handlePull(WP_REST_Request $request): WP_REST_Response
    {
        $source = (string) $request->get_param('source');
        if (!in_array($source, SyncEnv::allowedSources(), true)) {
            return new WP_REST_Response([
                'ok'     => false,
                'reason' => sprintf('Source "%s" is not permitted from the %s environment.', $source, SyncEnv::current()),
            ], 400);
        }

        $wp = $this->wpBinary();
        if ($wp === null) {
            return new WP_REST_Response(['ok' => false, 'reason' => 'Could not locate the wp-cli binary on this server.'], 500);
        }

        $user  = wp_get_current_user();
        $actor = ($user && $user->user_login) ? $user->user_login : 'unknown';
        $force = (bool) $request->get_param('force');

        @set_time_limit(600);
        $cmd = SyncCommandBuilder::build($wp, $source, $actor, rtrim(ABSPATH, '/'), $force);
        exec($cmd, $out, $code);

        // The staleness guard blocked it (source is behind this env). Surface it as a
        // distinct, non-error outcome so the button can offer an informed override
        // rather than treat it as a failure.
        if ($code === SyncCommand::EXIT_STALE) {
            return new WP_REST_Response([
                'ok'     => false,
                'stale'  => true,
                'source' => $source,
                'env'    => SyncEnv::current(),
                'reason' => trim(implode("\n", $out)),
            ], 409);
        }

        return new WP_REST_Response([
            'ok'     => $code === 0,
            'source' => $source,
            'env'    => SyncEnv::current(),
            'output' => implode("\n", $out),
        ], $code === 0 ? 200 : 500);
    }

    public function printScript(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, SettingsHubProvider::PAGE_SLUG) === false) {
            return;
        }
        $endpoint = esc_url_raw(rest_url(self::REST_NS . self::REST_ROUTE));
        $nonce    = wp_create_nonce('wp_rest');
        ?>
        <style>
        #arthouse-sync-log { display:none; background:#1e1e1e; color:#e0e0e0; padding:12px; margin-top:8px; max-height:360px; overflow:auto; white-space:pre-wrap; }
        #arthouse-sync-log.is-visible { display:block; }
        </style>
        <script>
        (function () {
            var endpoint = <?php echo wp_json_encode($endpoint); ?>;
            var nonce    = <?php echo wp_json_encode($nonce); ?>;
            var log      = document.getElementById('arthouse-sync-log');
            var spinner  = document.querySelector('.arthouse-sync-spinner');

            function setBusy(busy) {
                document.querySelectorAll('.arthouse-sync-btn').forEach(function (b) { b.disabled = busy; });
                if (spinner) { spinner.classList.toggle('is-active', busy); }
            }

            function pull(source, force) {
                setBusy(true);
                if (log) { log.classList.add('is-visible'); log.textContent = 'Pulling from ' + source + '… (this can take a minute)'; }
                return fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                    body: JSON.stringify({ source: source, force: !!force })
                })
                .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
                .then(function (res) {
                    var b = res.body || {};
                    // The staleness guard blocked it — offer an informed override.
                    if (b.stale) {
                        setBusy(false);
                        if (window.confirm((b.reason || (source + ' is behind this environment.')) + '\n\nPull anyway and DISCARD this environment’s newer content?')) {
                            return pull(source, true);
                        }
                        if (log) { log.textContent = 'Cancelled — nothing was changed.'; }
                        return;
                    }
                    if (log) { log.textContent = (b.ok ? '✓ Done — reload to see the updated log.' : '✗ Failed' + (b.reason ? ': ' + b.reason : '') + '.') + '\n\n' + (b.output || b.reason || ''); }
                    setBusy(false);
                })
                .catch(function (e) { if (log) { log.textContent = '✗ Request error: ' + e; } setBusy(false); });
            }

            document.querySelectorAll('.arthouse-sync-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var source = btn.getAttribute('data-source');
                    if (!window.confirm('This OVERWRITES this environment’s database and uploads with ' + source + '. Continue?')) {
                        return;
                    }
                    pull(source, false);
                });
            });
        })();
        </script>
        <?php
    }

    private function migrationHtml(): string
    {
        $env     = SyncEnv::current();
        $allowed = SyncEnv::allowedSources();
        $labels  = ['staging' => 'Staging', 'production' => 'Production'];
        $domain  = $this->textDomain();

        ob_start();
        ?>
        <p><?php printf(
            /* translators: %s: current environment */
            esc_html__('You are on %s. Pulling copies that environment\'s database and uploads down onto this one and overwrites it. One-way only — it never writes to a higher environment.', $domain),
            '<strong>' . esc_html($env) . '</strong>'
        ); ?></p>

        <?php if ($allowed === []) : ?>
            <p><em><?php esc_html_e('No sync sources are available in this environment.', $domain); ?></em></p>
        <?php else : ?>
            <p>
                <?php foreach ($allowed as $src) : ?>
                    <button type="button" class="button button-secondary arthouse-sync-btn" data-source="<?php echo esc_attr($src); ?>" style="margin-right:8px;">
                        <?php echo esc_html(sprintf(__('Pull from %s', $domain), $labels[$src] ?? ucfirst($src))); ?>
                    </button>
                <?php endforeach; ?>
                <span class="spinner arthouse-sync-spinner" style="float:none;"></span>
            </p>
            <pre id="arthouse-sync-log"></pre>
        <?php endif; ?>

        <h4 style="margin-top:20px;"><?php esc_html_e('Recent pulls', $domain); ?></h4>
        <?php $entries = SyncEnv::readLog(); ?>
        <?php if ($entries === []) : ?>
            <p><em><?php esc_html_e('No pulls recorded yet.', $domain); ?></em></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:820px;">
                <thead><tr>
                    <th><?php esc_html_e('When (UTC)', $domain); ?></th>
                    <th><?php esc_html_e('Who', $domain); ?></th>
                    <th><?php esc_html_e('From → To', $domain); ?></th>
                    <th><?php esc_html_e('Result', $domain); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($entries as $e) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($e['time'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($e['actor'] ?? '')); ?></td>
                        <td><?php echo esc_html(($e['source'] ?? '?') . ' → ' . ($e['target'] ?? '?')); ?></td>
                        <td><?php echo empty($e['ok']) ? '&#10007; failed' : '&#10003; success'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    private function wpBinary(): ?string
    {
        foreach (['/usr/local/bin/wp', '/usr/bin/wp'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        $which = trim((string) @shell_exec('command -v wp 2>/dev/null'));

        return $which !== '' ? $which : null;
    }

    /**
     * Text domain for the (admin-only) tab labels. Override in a thin per-site
     * subclass to fold these strings into the site's own .po; defaults to the kit's.
     */
    protected function textDomain(): string
    {
        return 'arthouse-kit';
    }
}
