# arthouse-kit

Shared, vendor-coupled providers for the **ARTHOUSE** marketing sites (Celebrity
Autobiography, Matchbook Festival, A View From The Bridge) on the **Mythus + IX**
WordPress stack. Consolidates code that was copy-pasted between those child themes.

Distributed as a Composer library via the `packages.vincentragosta.io` satis registry.
It is **not** used by the non-marketing consumers (vincentragosta.io, ellenharvey),
which is why this vendor-coupled code lives here rather than in Mythus/IX.

## Design

Each provider is an **abstract base** that a child theme subclasses with per-site
config. The base owns all shared behaviour; the child supplies only the brand-specific
bits (hub group key, ACF field-key prefix, theme colour, text domain). ACF field
**keys stay per-site** (`field_mbf_*`, `field_avftb_*`, …) so adopting the kit never
orphans stored option values.

## Providers

| Provider | Status | What it owns |
|---|---|---|
| `Arthouse\Providers\SeoProvider` | v0.1.0 | **Raw SEO mode** — SEO tab + a client-managed raw JSON-LD field (validated on save, safe-encoded) + OG/Twitter tags |
| `Arthouse\Providers\AssembledSeoProvider` | v0.2.0 | **Assembled SEO mode** — auto-built `TheaterEvent` from structured fields + live cast (`performers()`) + optional additions/override field |
| Analytics / Segment | planned | Segment loader + consent modal, CMS write key (blank = disabled) |
| Newsletter / Campaign Monitor | planned | CM subscribe endpoint (Routable), optional second list |

## Install (in a child theme)

```json
{
  "require": {
    "vincentragosta/arthouse-kit": "^0.1"
  }
}
```

Then subclass in the child theme:

```php
final class SeoProvider extends \Arthouse\Providers\SeoProvider
{
    protected function hubGroupKey(): string { return SettingsProvider::GROUP_KEY; }
    protected function keyPrefix(): string   { return 'mbf'; }
    protected function themeColor(): string  { return '#F5C53A'; }
    protected function textDomain(): string  { return 'matchbook-festival'; }
}
```

## Stack

- PHP 8.4+, strict types. Depends on `vincentragosta/ix` (+ `vincentragosta/mythus`).
- Providers extend `IX\Providers\Provider` and register into the child's Settings hub.
