# Upgrading arthouse-kit

Pre-1.0, minor versions may include breaking changes; they're called out here with a
migration note. Adoption is per-child-theme and reversible (re-pin + `composer update`,
restore the in-tree provider).

## Adopting the SEO provider (from an in-theme `SeoProvider`)

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

Because `keyPrefix()` preserves the field keys, **no ACF value migration is required**.
