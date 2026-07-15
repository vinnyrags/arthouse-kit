# Changelog

All notable changes to arthouse-kit are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions are derived from
annotated git tags (no `version` field in `composer.json`).

## [Unreleased]

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

[Unreleased]: https://github.com/vinnyrags/arthouse-kit/compare/v0.2.0...HEAD
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
