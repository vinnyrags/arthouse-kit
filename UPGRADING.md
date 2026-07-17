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

## Adopting the Newsletter provider (from an in-theme `NewsletterProvider`)

The kit's `Arthouse\Providers\Newsletter\NewsletterProvider` is the shared Campaign
Monitor signup (block + REST endpoint + JS + hub tab). Adoption is per-child-theme and
reversible (re-pin + `composer update`, restore the in-tree provider + assets, reverse
the block-name `search-replace`).

**Prerequisites.** The site is already on the Settings hub with canonical
`field_arthouse_newsletter_*` keys, so **no ACF value migration is needed**. The
Campaign Monitor API key + list ID must be set in the CMS (Site Settings → Campaign
Monitor) on **every environment** — blank disables signups, which the live subscribe
test catches. The child's `scripts/build-providers.config.js` already lists the kit
`extraProviderDirs` (from the Analytics adoption).

### Two adoption modes

- **Shared block (CBA, AVFTB):** drop the in-theme block entirely and adopt the
  canonical `arthouse/newsletter` block from the kit. This renames the registered
  block, so **stored block instances must be renamed** in the DB (see step 4).
- **Own block (MF):** keep the theme's own `blocks/newsletter/` (its own markup +
  SCSS). Because the block search path is child-first, the theme's block.json
  overrides the kit's — the registered name stays `matchbookfestival/newsletter`, so
  **no DB rename**. Only the PHP provider + endpoint + JS are shared.

### Steps (per site, staging first)

1. Pin `"vincentragosta/arthouse-kit": "^0.6"` → `composer update
   vincentragosta/arthouse-kit` **in the child theme dir** (watch the transitive `ix`
   doesn't regress). Drop `campaignmonitor/createsend-php` from the child
   `composer.json` if present (only CBA had it).
2. **PHP:** replace the in-theme `NewsletterProvider` with a thin subclass of
   `Arthouse\Providers\Newsletter\NewsletterProvider`, overriding only what differs:
   `textDomain()`; `formId()` + `sendsNonce()` for a nonce-less anonymous form (MF:
   `'subForm'` / `false`); `apiKeyFieldType()` (`'password'` for AVFTB). Delete the
   in-theme `Endpoints/`, `assets/js/`, and — in shared-block mode — the whole
   `blocks/newsletter/`. In own-block mode, keep `blocks/newsletter/`. **The partner
   opt-in + fineprint need no code** — see step 3a.
3. **Brand token map:** in a globally-loaded stylesheet (the Theme provider's
   `index.scss`), add a `:root` map for the `--newsletter-*` props the site uses
   (`--newsletter-input-text`, `--newsletter-input-bg`, and for an opt-in site
   `--newsletter-optin-color` / `--newsletter-fineprint-color`). A site that wants the
   iOS Safari button-stretch fix layers it here too (`.newsletter-form__submit {
   display: flex; align-items: stretch } .newsletter-form__button { height: 100% }`) —
   it's intentionally not in the shared SCSS because it alters the button box.
3a. **Partner opt-in / fineprint (CMS, no code).** The optional second-list opt-in and
   consent fineprint are generic, CMS-driven fields on the Campaign Monitor tab
   (registered by the kit for every site, blank = off). To enable them, fill in Site
   Settings → Campaign Monitor: **Partner Opt-in — Checkbox Label**, **Partner Opt-in
   — Campaign Monitor List ID**, and **Consent Fineprint**. The block renders the
   checkbox (`name="optin"`) + fineprint from those values and the endpoint routes the
   best-effort second subscribe. A site that previously stored a value under the old
   `campaign_monitor_lamama_list_id` name moves it to `campaign_monitor_optin_list_id`
   (a value re-entry, or a one-line `wp option` / ACF update on each env).
4. **Shared-block mode only — rename stored block instances.** On a DB backup, run
   `wp search-replace '{theme}/newsletter' 'arthouse/newsletter' --all-tables-with-prefix`
   (or scoped to the content-partial CPT). The block lives in the footer content
   partial; instances render as "unregistered" until renamed, so run this right after
   the deploy that registers the canonical block. Reversible with the inverse replace.
   The wrapper class changes `wp-block-{theme}-newsletter → wp-block-arthouse-newsletter`
   — a deliberate, styling-neutral delta (the SCSS is site-agnostic).
5. **Build + verify on staging.** `npm run build`; confirm the form renders, the
   Campaign Monitor tab shows on the hub, and a **live test subscribe** returns
   `{ ok: true }` and lands in the correct CM list (+ the La MaMa list when the box is
   ticked, for AVFTB). Prove 1:1 parity vs prod: form markup byte-identical (normalise
   `?ver=` and the deliberate wrapper-class rename), the newsletter JS behaviourally
   identical (config-driven branches inert per site + the `arthouseNewsletter` global),
   and the form's computed styles resolve to the same palette values. Then ship to prod
   (own-block mode: just deploy; shared-block mode: deploy **and** re-run step 4 on the
   prod DB).

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
