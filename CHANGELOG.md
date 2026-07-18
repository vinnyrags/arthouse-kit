# Changelog

All notable changes to arthouse-kit are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions are derived from
annotated git tags (no `version` field in `composer.json`).

## [Unreleased]

## [0.8.1] - 2026-07-17

### Fixed

- **Migration tab no longer collides with a child theme's "Advanced" tab.**
  `ORDER_MIGRATION` was `90` — the same `menu_order` lane child themes
  conventionally use for their own developer/"Advanced" Site Settings tab (e.g.
  AVFTB's typekit tab). Two tabs tied at 90 sort unstably in ACF, so the Migration
  message field interleaved *under* the Advanced tab. Moved `ORDER_MIGRATION` to
  `100` so the ops-only Migration tab always sorts last, clear of that lane.

## [0.8.0] - 2026-07-17

### Added

- **Sync admin surface — the Migration tab that drives the Mythus sync engine.**
  Two providers under `Arthouse\Providers\Sync`:

  - `SyncSettingsProvider` — adds a **Migration** tab to the shared Settings hub
    (ad-hoc, canonical `field_arthouse_migration_*` keys) with env-aware one-way
    "Pull from Staging/Production" buttons + a recent-pulls activity log. The tab
    only appears where a pull is possible (`local`/`staging`), never on production.
    Its `POST /wp-json/theme/v1/sync/pull` route (manage_options) shells out via
    `Mythus\Support\Sync\SyncCommandBuilder::build()`.
  - `SyncCliProvider` — registers the `wp mythus sync-from <staging|production>`
    CLI command (`Mythus\Support\Sync\SyncCommand`).

  Generalized from the AVFTB-native `Providers/Sync` (whose keys/classes were
  `field_avftb_*` / `avftb-sync-*`); the engine itself now lives in Mythus 1.3.
  A site enables Sync purely by configuring the `mythus/sync/sources` filter and
  registering these two providers — no per-site UI/CLI code.

### Changed

- **Bumped the `vincentragosta/mythus` constraint to `^1.3`** — the Sync providers
  depend on the `Mythus\Support\Sync\*` engine shipped in Mythus 1.3.0.

## [0.7.0] - 2026-07-16

### Changed

- **Newsletter partner opt-in is now generic + CMS-driven — no per-show content in
  the kit.** The optional second-list opt-in (used by AVFTB for La MaMa) is no longer
  a per-site config surface baked into code. The kit registers three generic ACF
  fields on the Campaign Monitor hub tab for **every** site, blank by default:
  `campaign_monitor_optin_label` (checkbox label — blank hides the opt-in),
  `campaign_monitor_optin_list_id` (the second CM list), and `newsletter_fineprint`
  (consent copy under the form). A site enables the opt-in purely by filling these in
  the CMS — **zero theme code, no "La MaMa" or any show specifics in the kit.**
  - `render.php` builds the opt-in checkbox (`name="optin"`) + fineprint from those
    values into a whitespace-safe output slot; the endpoint routes the best-effort
    second subscribe when `optin` is set and `campaign_monitor_optin_list_id` is
    configured; the JS auto-includes any named control, so the opt-in flows through
    without the kit knowing field names.
  - **Removed** the `laMamaListField()` / `optinLabel()` / `fineprint()` config
    methods and the `campaign_monitor_lamama_list_id` field (nothing consumed them
    yet). A site migrating a stored La MaMa list value moves it to
    `campaign_monitor_optin_list_id` (see UPGRADING).

### Fixed

- **Newsletter block twig whitespace (supersedes 0.6.1).** Replaced the opt-in/
  fineprint `{% if %}` conditionals with a single `{{ extra_fields | raw }}` output
  slot built in `render.php`. An output tag leaves surrounding whitespace untouched
  regardless of Timber's `trim_blocks`/`lstrip_blocks`, so the rendered markup is
  byte-identical to the pre-kit per-site output whether the extra fields are present
  or absent (0.6.1's plain-`{% if %}` approach still left stray indentation).

[0.7.0]: https://github.com/vinnyrags/arthouse-kit/compare/v0.6.1...v0.7.0

## [0.6.1] - 2026-07-16

### Fixed

- **Newsletter block twig whitespace.** The La MaMa opt-in/fineprint conditionals
  used Twig left-trim (`{%- … %}`), which — on top of Timber's `trim_blocks` /
  `lstrip_blocks` — over-collapsed the newline between the form's closing `</div>`
  and `</form>` when the section was absent. Switched to plain `{% if %}` / `{% endif %}`
  (the whitespace-neutral pattern the `required_label` block already used), so the
  rendered markup is byte-identical to the pre-kit per-site output in both the
  absent (CBA) and present (AVFTB) cases.

[0.6.1]: https://github.com/vinnyrags/arthouse-kit/compare/v0.6.0...v0.6.1

## [0.6.0] - 2026-07-16

### Added

- **`Arthouse\Providers\Newsletter\NewsletterProvider`** + **`…\Newsletter\Endpoints\NewsletterSubscribeEndpoint`**
  — the shared Campaign Monitor signup, extracted from the near-identical per-site
  copies on CBA/MF/AVFTB. Owns the `arthouse/newsletter` block, the
  `/wp-json/theme/v1/newsletter/subscribe` REST endpoint (a `Mythus` `Routable`),
  the frontend handler, and the "Campaign Monitor" tab on the Settings hub with
  canonical `field_arthouse_newsletter_*` keys (no ACF value migration). Credentials
  are CMS-only; a blank API key or list ID disables signups (fail-safe off) and skips
  shipping the JS.
  - **CM call is `wp_remote_post`** (CM v3.3 API, Basic `base64(apiKey:x)`), so the
    `campaignmonitor/createsend-php` dependency is dropped — lighter and uniform.
    Trade-off: CBA's richer CM error-message parsing is gone (the form only ever
    showed a generic "sorry, try again").
  - **JS global canonicalised to `arthouseNewsletter`** (was `cbaNewsletterConfig` /
    `avftbNewsletter` / `mbfNewsletter`). One config-driven handler: `formId` picks
    the form, `nonce` present → `X-WP-Nonce` / absent → `credentials: 'omit'`, and
    the La MaMa opt-in field is only sent when its checkbox is present.
  - **Optional second list (AVFTB La MaMa):** the endpoint always accepts a
    best-effort `lamama_optin`, routing a second subscribe only when a
    `campaign_monitor_lamama_list_id` option is set (inert everywhere else). A site
    surfaces the field + checkbox by overriding `laMamaListField()` / `optinLabel()`
    / `fineprint()`; those feed the block via `NewsletterProvider::BLOCK_CONTEXT_FILTER`
    (copy stays in the subclass — no per-instance ACF/DB migration).
  - **Block SCSS is palette-agnostic** (semantic `--newsletter-*` props with neutral
    fallbacks) and **site-agnostic** — the max-width uses a doubled `.wp-block-newsletter`
    selector instead of the per-site `wp-block-{theme}-newsletter` auto class, so the
    block can register under the canonical `arthouse/newsletter` name (a site renames
    its stored block instances via `search-replace`; see UPGRADING.md). Provider JS/SCSS
    compile into the child `dist/` via IX's `extraProviderDirs`.
  - **Config surface** (thin subclass): `textDomain()`, `formId()`, `sendsNonce()`,
    `apiKeyFieldType()`, `laMamaListField()`, `optinLabel()`, `fineprint()`.

[0.6.0]: https://github.com/vinnyrags/arthouse-kit/compare/v0.5.0...v0.6.0

## [0.5.0] - 2026-07-16

### Added

- **`Arthouse\Providers\Analytics\AnalyticsProvider`** — the shared Segment loader
  + Segment Consent Manager modal, extracted from the (byte-identical bar the JS
  global name + palette) per-site copies on CBA/MF/AVFTB. Loads on staging + prod
  only; the Segment write key is **CMS-only** (Site Settings → Analytics), blank
  disables Segment + the modal (fail-safe off). Registers its tab ad-hoc onto the
  Settings hub with canonical `field_arthouse_analytics_*` keys (no data migration
  for sites already on those keys). The JS global is canonicalised to
  `arthouseAnalytics`; the modal SCSS is palette-agnostic (semantic `--consent-*`
  props with neutral fallbacks) — each child ships a short `:root` token map
  (see `UPGRADING.md`). Provider JS/SCSS compile into the child `dist/` via IX's
  new `extraProviderDirs` build config; no vendor-path asset URLs.

[0.5.0]: https://github.com/vinnyrags/arthouse-kit/compare/v0.4.0...v0.5.0

## [0.4.0] - 2026-07-16

### Changed (BREAKING for AssembledSeoProvider consumers)

- **`AssembledSeoProvider` reads canonical field names**: `meta_keywords` → `keywords`
  and `schema_poster` → `schema_image`, aligning the assembled-mode option names with
  the raw `SeoProvider` and across sites. Adopting sites must rename the stored option
  value rows (`options_meta_keywords` → `options_keywords`, `options_schema_poster` →
  `options_schema_image`) — a value-move, not just a key repoint. See `UPGRADING.md`.

[0.4.0]: https://github.com/vinnyrags/arthouse-kit/compare/v0.3.0...v0.4.0

## [0.3.0] - 2026-07-15

### Added

- **`Arthouse\Providers\SettingsHubProvider`** — the shared "Site Settings" hub: a
  single ACF options page (`arthouse-site-settings`) + one empty field group
  (`group_arthouse_site_settings`) that every site registers, so all sites get one
  consolidated, top-tabbed settings screen instead of a scatter of options pages.
  Other providers add their tab + fields to `SettingsHubProvider::GROUP_KEY` ad-hoc;
  `ORDER_*` constants give predictable tab ordering. Keys are **canonical** and
  identical across sites — the platform no longer accommodates per-site prefixes.
  - Fields are sorted by `menu_order` via an `acf/load_fields` filter scoped to the
    hub group, so the `ORDER_*` lanes are authoritative — tab order is identical on
    every site regardless of which providers load or their acf/init priority (ACF
    renders ad-hoc local fields in insertion order otherwise).

### Changed (BREAKING)

- **`Arthouse\Providers\SeoProvider` retrofitted to canonical keys.** Dropped the
  `hubGroupKey()` and `keyPrefix()` abstract methods; the SEO tab now registers into
  `SettingsHubProvider::GROUP_KEY` with canonical `field_arthouse_seo_*` keys (was
  `field_{prefix}_*` per site). The class is no longer abstract — a child subclasses
  it only to set `themeColor()` / `textDomain()`.
  - **Migration required on adoption:** stored option **values** are keyed by name and
    survive, but each field's key-reference row (`_options_{name}`) must be repointed to
    the new canonical key, or ACF orphans the value in admin. See `UPGRADING.md`.
  - `AssembledSeoProvider` is unaffected (it reads by name and registers no fields).

[0.3.0]: https://github.com/vinnyrags/arthouse-kit/compare/v0.2.1...v0.3.0

## [0.2.1] - 2026-07-15

### Changed

- Relax the PHP requirement `>=8.4` → `>=8.3` to match IX and Mythus (some ARTHOUSE
  droplets run 8.3; the kit uses no 8.4-only syntax).

[0.2.1]: https://github.com/vinnyrags/arthouse-kit/compare/v0.2.0...v0.2.1

## [0.2.0] - 2026-07-15

### Added

- **`Arthouse\Providers\AssembledSeoProvider`** — the "assembled" SEO mode, the
  auto-generated counterpart to the raw `SeoProvider`. Composes IX's
  `SchemaBuilderService` into a `TheaterEvent` from the site's structured
  Site-Options fields (venue, address, dates, tickets, organizer, images) plus a
  **live cast list** folded in via `performers()` (defaults to all published posts
  of `performerPostType()` — `talent` by default — mapped to `schema.org/Person`;
  override for a different source). A `performersFromPostType()` helper covers the
  common cast case in one line.
  - **Hybrid extension:** an optional `schema_additions` option (raw JSON-LD object)
    is **top-level merged** over the assembled payload — its keys add to or override
    the generated ones. Blank = no effect.
  - Reads options by name and does **not** register fields (the site owns its
    Site-Options layout), so adopting it adds no admin fields.
  - Child config: `organizerFallback()`, `themeColor()`, `performerPostType()`,
    `metaCommentLabel()`.

[Unreleased]: https://github.com/vinnyrags/arthouse-kit/compare/v0.4.0...HEAD
[0.2.0]: https://github.com/vinnyrags/arthouse-kit/compare/v0.1.0...v0.2.0

## [0.1.0] - 2026-07-15

### Added

- Initial package: shared providers for the ARTHOUSE marketing sites on Mythus + IX.
- **`Arthouse\Providers\SeoProvider`** — abstract base a child theme subclasses with
  four config methods (`hubGroupKey()`, `keyPrefix()`, `themeColor()`, `textDomain()`).
  Owns the SEO tab (page title, site/brand name, meta description, keywords, share
  image), the Open Graph / Twitter tags, and a **client-managed raw JSON-LD field**:
  validated as JSON on save (`acf/validate_value`) and decoded + re-encoded with
  `JSON_HEX_TAG` on output (only valid, escape-safe JSON emitted; `</script>` can't
  break out; blank omits the schema). ACF keys stay per-site via `keyPrefix()`, so
  adoption needs no stored-value migration.

[0.1.0]: https://github.com/vinnyrags/arthouse-kit/releases/tag/v0.1.0
