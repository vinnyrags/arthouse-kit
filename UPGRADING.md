# Upgrading arthouse-kit

Pre-1.0, minor versions may include breaking changes; they're called out here with a
migration note. Adoption is per-child-theme and reversible (re-pin + `composer update`,
restore the in-tree provider).

## Adopting the Analytics provider (from an in-theme `AnalyticsProvider`)

The kit's `Arthouse\Providers\Analytics\AnalyticsProvider` is the shared Segment +
Consent Manager modal. Adoption is per-child-theme and reversible (re-pin +
`composer update`, restore the in-tree provider + assets).

**Prerequisites.** The site is already on the Settings hub with canonical
`field_arthouse_analytics_*` keys (shipped with the hub in 0.3.0), so **no ACF value
migration is needed** — only code moves. The Segment write key must be set in the CMS
(Site Settings → Analytics) on **every environment** before removing any in-theme
default; a blank key disables Segment + the modal.

### Steps (per site, staging first)

1. Pin `"vincentragosta/arthouse-kit": "^0.5"` and `composer update
   vincentragosta/arthouse-kit`. (For local iteration, a Composer `path` repo
   pointed at the kit source works; remove it before committing.)
2. **Build config:** in the child's `scripts/build-providers.config.js`, add
   `extraProviderDirs: [ <path to> vendor/vincentragosta/arthouse-kit/src/Providers ]`
   so the kit's Analytics JS/SCSS compile into the child `dist/` (needs IX ≥ the
   `extraProviderDirs` release). Output paths are identical to the in-theme provider
   (`dist/css/analytics.css`, `dist/js/analytics/index.js`).
3. **PHP:** replace the child's `AnalyticsProvider` with a thin subclass of
   `Arthouse\Providers\Analytics\AnalyticsProvider` (optionally overriding
   `textDomain()` / `shouldLoad()`), or register the kit class directly. Delete the
   in-theme `assets/` (JS + SCSS) — they now come from the kit.
4. **Brand token map:** in a globally-loaded stylesheet (e.g. the Theme provider's
   `index.scss`), add a `:root` map pointing the semantic `--consent-*` props at the
   site palette. Only list deviations from the kit fallbacks. Minimum is usually
   surface / text / font / heading-font / accent; add `--consent-accent-hover`,
   `--consent-heading-size`, `--consent-button-size`, `--consent-accent-contrast`,
   `--consent-link[-hover]`, `--consent-heading-weight`, `--consent-body-size`,
   `--consent-shadow`, `--consent-radius`, `--consent-overlay` as the site requires.
5. **Verify on staging** (the env gate means local dev never loads Segment): the
   consent modal renders in the site palette, Segment loads + fires (EU/UK sees the
   modal; non-EU auto-consents), and a blank write key disables both. Then ship to
   prod.

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
