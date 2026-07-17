<?php

declare(strict_types=1);

namespace Arthouse\Providers\Sync;

use IX\Providers\Provider;
use Mythus\Support\Sync\SyncCommand;
use WP_CLI;

/**
 * Registers the `wp mythus sync-from <staging|production>` CLI command — the
 * one-way "pull a higher env down" engine that lives in Mythus
 * ({@see \Mythus\Support\Sync\SyncCommand}). The Migration-tab buttons
 * ({@see SyncSettingsProvider}) shell out to this same command.
 *
 * The engine is inert until a site configures the `mythus/sync/sources` filter, so
 * registering the command everywhere is harmless — an unconfigured site's command
 * errors out cleanly.
 */
final class SyncCliProvider extends Provider
{
    public function register(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('mythus sync-from', SyncCommand::class);
        }

        parent::register();
    }
}
