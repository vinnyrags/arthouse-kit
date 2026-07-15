<?php

declare(strict_types=1);

namespace Arthouse\Providers;

use IX\Providers\Provider;

/**
 * The shared "Site Settings" hub — a single ACF options page + one field group
 * that every ARTHOUSE site registers, so all sites get the same consolidated
 * settings screen instead of a scatter of separate options pages.
 *
 * Other providers (SEO, Analytics, Newsletter, Social, and any site-specific
 * ones) add their own tab + fields to {@see GROUP_KEY} ad-hoc via
 * acf_add_local_field(['parent' => self::GROUP_KEY, ...]) — so each provider
 * keeps ownership of its fields, in its own file, while they all render as one
 * unified, top-placed tab bar (same group ⇒ merged tabs), ordered by menu_order.
 *
 * Keys are **canonical** (`group_arthouse_site_settings`, `field_arthouse_*`),
 * identical across sites — the platform no longer accommodates per-site prefixes.
 */
class SettingsHubProvider extends Provider
{
    /** Canonical hub group key — providers add their tabs/fields here. */
    public const GROUP_KEY = 'group_arthouse_site_settings';

    /** Canonical options-page slug. */
    public const PAGE_SLUG = 'arthouse-site-settings';

    /** Menu-order lanes so provider tabs land in a predictable order. */
    public const ORDER_SHOW     = 10;
    public const ORDER_SEO      = 20;
    public const ORDER_ANALYTICS = 30;
    public const ORDER_NEWSLETTER = 40;
    public const ORDER_SOCIAL    = 50;

    public function register(): void
    {
        add_action('acf/init', [$this, 'registerOptionsPage']);
        add_action('acf/init', [$this, 'registerHubGroup'], 5);
        add_filter('acf/load_fields', [$this, 'sortHubFields'], 20, 2);

        parent::register();
    }

    /**
     * Order the hub's tabs/fields by menu_order.
     *
     * Providers append their fields ad-hoc, so the natural order is insertion
     * (hook-firing) order — fragile and dependent on each provider's acf/init
     * priority. This makes menu_order authoritative instead: that's what the
     * ORDER_* lanes are for, and it's what keeps the tab layout identical across
     * every site regardless of which providers a site loads or in what order.
     *
     * A flat menu_order sort yields correct tab grouping because each provider
     * lays its tab at ORDER_x and its fields at ORDER_x + n (n ≥ 1), within the
     * lane before the next tab. usort is stable on PHP 8.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param mixed                            $parent The field group (array) or its key.
     * @return array<int, array<string, mixed>>
     */
    public function sortHubFields(array $fields, $parent): array
    {
        $key = is_array($parent) ? ($parent['key'] ?? '') : $parent;
        if ($key !== self::GROUP_KEY) {
            return $fields;
        }
        usort($fields, static fn ($a, $b): int => ($a['menu_order'] ?? 0) <=> ($b['menu_order'] ?? 0));

        return $fields;
    }

    public function registerOptionsPage(): void
    {
        if (!function_exists('acf_add_options_page')) {
            return;
        }
        acf_add_options_page([
            'page_title' => $this->pageTitle(),
            'menu_title' => $this->pageTitle(),
            'menu_slug'  => self::PAGE_SLUG,
            'capability' => 'manage_options',
            'icon_url'   => 'dashicons-admin-settings',
            'position'   => 59,
            'redirect'   => false,
        ]);
    }

    public function registerHubGroup(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }
        acf_add_local_field_group([
            'key'      => self::GROUP_KEY,
            'title'    => $this->pageTitle(),
            'fields'   => [],
            'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => self::PAGE_SLUG]]],
        ]);
    }

    /** The page + menu title. Override per site if a different label is wanted. */
    protected function pageTitle(): string
    {
        return 'Site Settings';
    }
}
