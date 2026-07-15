# Upgrading arthouse-kit

Pre-1.0, minor versions may include breaking changes; they're called out here with a
migration note. Adoption is per-child-theme and reversible (re-pin + `composer update`,
restore the in-tree provider).

## 0.2.x → 0.3.0 — canonical keys + Settings hub (BREAKING)

0.3.0 moves to **canonical, cross-site ACF keys**: the raw `SeoProvider` no longer
takes a `keyPrefix()` / `hubGroupKey()`, and registers into the shared
`SettingsHubProvider` group (`group_arthouse_site_settings`) with `field_arthouse_seo_*`
keys. This aligns every site on one key scheme instead of the platform accommodating
per-site prefixes — at the cost of a **one-time rekey migration** of stored options.

### Why a migration is needed (and why it's safe)

ACF stores each option field as two `wp_options` rows: `options_{name}` (the **value**)
and `_options_{name}` (a pointer to the field's **key**). Values are keyed by **name**,
which is unchanged — so **no value is lost**. Only the pointer rows must be repointed
from the old per-site key to the new canonical key, or ACF shows the field blank in
admin (the value is still in the DB, just disconnected from the field def).

### Steps (per site, staging first, on a DB backup)

1. **Back up** `wp_options` (`wp db export` or a scoped dump of the `options_%`/`_options_%`
   rows). Rehearse rollback.
2. Adopt the hub: register `Arthouse\Providers\SettingsHubProvider` and repoint the
   child's SEO subclass (drop the `keyPrefix()`/`hubGroupKey()` overrides).
3. **Rekey** the SEO pointer rows to the canonical keys — for each field `name`:
   `update_option('_options_{name}', 'field_arthouse_seo_{name}')`. Idempotent; the
   `options_{name}` value row is left untouched.
4. Verify on staging: the SEO tab renders in the hub with **every saved value intact**,
   `wp_head` emits identical meta/OG tags, and the JSON-LD round-trips.
5. Ship to prod behind the same backup/rollback.

## Adopting the SEO provider (from an in-theme `SeoProvider`) — pre-0.3.0

1. Add `"vincentragosta/arthouse-kit": "^0.1"` to the child theme's `composer.json`,
   `composer update`.
2. Replace the child's `SeoProvider` with a thin subclass of
   `Arthouse\Providers\SeoProvider`, implementing `hubGroupKey()` + `keyPrefix()`
   (and optionally `themeColor()` / `textDomain()`). **Set `keyPrefix()` to the site's
   existing prefix** (`mbf`, `avftb`, …) so the ACF field keys — and the stored option
   values behind them — are unchanged.
3. Verify: the SEO tab still renders in the hub with its saved values, `wp_head`
   emits the same meta/OG tags, and the JSON-LD field round-trips (save an invalid
   block → rejected; a valid block → rendered).
4. Delete the in-theme `SeoProvider` file once staging is verified.
