# Changelog

All notable changes to arthouse-kit are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions are derived from
annotated git tags (no `version` field in `composer.json`).

## [Unreleased]

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

[Unreleased]: https://github.com/vinnyrags/arthouse-kit/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/vinnyrags/arthouse-kit/releases/tag/v0.1.0
