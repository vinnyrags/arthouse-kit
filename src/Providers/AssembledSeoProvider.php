<?php

declare(strict_types=1);

namespace Arthouse\Providers;

use IX\Providers\Provider;
use IX\Services\SchemaBuilderService;

/**
 * "Assembled" SEO provider for ARTHOUSE show sites — the auto-generated counterpart
 * to the raw {@see SeoProvider}.
 *
 * Instead of a client-pasted JSON-LD block, this composes IX's SchemaBuilderService
 * into a TheaterEvent from the site's structured Site-Options fields (venue, address,
 * dates, tickets, organizer, images) plus a live cast list. A child theme supplies
 * only per-site config: the organizer fallback, the theme colour, and — if it isn't
 * the default `talent` — the performer post type.
 *
 * The cast is folded in automatically via {@see performers()}, which defaults to all
 * published posts of the performer post type mapped to schema.org/Person. A site with
 * no such post type simply gets an empty cast; a site with a different source overrides
 * performers() outright.
 *
 * Hybrid extension: an optional `schema_additions` option (raw JSON-LD, an object)
 * is TOP-LEVEL merged over the assembled payload — its keys add to, or override, the
 * generated ones. Left blank it does nothing.
 *
 * This provider READS option values by name; it does not register the fields (the
 * site owns its Site-Options layout), so adopting it changes no admin fields.
 */
abstract class AssembledSeoProvider extends Provider
{
    /** Organizer name when the `organizer_name` option is blank. */
    abstract protected function organizerFallback(): string;

    /** Brand colour for the theme-color meta tag. */
    protected function themeColor(): string
    {
        return '#000000';
    }

    /** Post type folded into the schema `performer` list. */
    protected function performerPostType(): string
    {
        return 'talent';
    }

    /** Brand label for the SEO head comment (`<!-- {label} SEO -->`). */
    protected function metaCommentLabel(): string
    {
        return 'ARTHOUSE';
    }

    public function register(): void
    {
        add_filter('document_title_separator', [$this, 'titleSeparator']);
        add_filter('pre_get_document_title', [$this, 'filterTitle']);
        add_action('wp_head', [$this, 'outputMetaTags'], 1);
        add_action('wp_head', [$this, 'outputSchema'], 5);

        parent::register();
    }

    public function titleSeparator(): string
    {
        return '|';
    }

    public function filterTitle(string $title): string
    {
        $name    = (string) get_bloginfo('name');
        $tagline = (string) get_bloginfo('description');

        if (is_front_page() || is_home()) {
            return $tagline ? "{$name} | {$tagline}" : $name;
        }

        return $title;
    }

    public function outputMetaTags(): void
    {
        $description = $this->getOption('meta_description');
        $keywords    = $this->getOption('keywords');
        $canonical   = $this->getCanonicalUrl();
        $ogImage     = $this->getOption('og_image');
        $name        = (string) get_bloginfo('name');
        $tagline     = (string) get_bloginfo('description');
        $siteTitle   = $tagline ? "{$name} | {$tagline}" : $name;
        $ogTitle     = is_front_page() ? $siteTitle : wp_get_document_title();

        echo "\n<!-- " . $this->metaCommentLabel() . " SEO -->\n";
        printf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical));

        if ($description) {
            printf('<meta name="description" content="%s" />' . "\n", esc_attr($description));
        }
        if ($keywords) {
            printf('<meta name="keywords" content="%s" />' . "\n", esc_attr($keywords));
        }

        printf('<meta property="og:url" content="%s" />' . "\n", esc_url($canonical));
        printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($ogTitle));
        echo '<meta property="og:type" content="website" />' . "\n";
        printf('<meta property="og:site_name" content="%s" />' . "\n", esc_attr($siteTitle));
        if ($description) {
            printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($description));
        }
        if ($ogImage) {
            printf('<meta property="og:image" content="%s" />' . "\n", esc_url($ogImage));
        }

        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        printf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($ogTitle));
        if ($description) {
            printf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr($description));
        }
        if ($ogImage) {
            printf('<meta name="twitter:image" content="%s" />' . "\n", esc_url($ogImage));
        }

        printf('<meta name="theme-color" content="%s" />' . "\n", esc_attr($this->themeColor()));
    }

    public function outputSchema(): void
    {
        if (!is_front_page()) {
            return;
        }

        $organizer = $this->getOption('organizer_name') ?: $this->organizerFallback();
        $builder = new SchemaBuilderService($organizer, home_url('/'));

        $payload = $this->buildTheaterEventPayload($builder);
        if ($additions = $this->additions()) {
            $payload = array_merge($payload, $additions);
        }

        $builder->add($payload);
        $json = $builder->toJson();
        if ($json === '') {
            return;
        }

        echo "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    /**
     * The auto-assembled TheaterEvent payload.
     *
     * @return array<string, mixed>
     */
    protected function buildTheaterEventPayload(SchemaBuilderService $builder): array
    {
        $description = $this->getOption('meta_description');
        $images      = array_values(array_filter([
            $this->getOption('schema_image'),
            $this->getOption('og_image'),
        ]));

        $payload = [
            '@context'    => 'https://schema.org',
            '@type'       => 'TheaterEvent',
            'name'        => (string) get_bloginfo('name'),
            'url'         => home_url('/'),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'organizer'   => $builder->organization(),
        ];

        if ($description) {
            $payload['description'] = $description;
        }
        if (!empty($images)) {
            $payload['image'] = $images;
        }
        if ($location = $this->buildLocation()) {
            $payload['location'] = $location;
        }
        if ($start = $this->getOption('event_start_date')) {
            $payload['startDate'] = $start;
        }
        if ($end = $this->getOption('event_end_date')) {
            $payload['endDate'] = $end;
        }
        if ($offers = $this->buildOffers()) {
            $payload['offers'] = $offers;
        }
        if ($performers = $this->performers()) {
            $payload['performer'] = $performers;
        }

        return $payload;
    }

    /**
     * The schema `performer` list. Defaults to all published posts of the performer
     * post type as schema.org/Person nodes; override for a different source.
     *
     * @return array<int, array<string, string>>
     */
    protected function performers(): array
    {
        return $this->performersFromPostType($this->performerPostType());
    }

    /**
     * Map published posts of a post type to schema.org person/group nodes
     * (menu_order then title). Convenience for the common cast-list case.
     *
     * @return array<int, array<string, string>>
     */
    protected function performersFromPostType(string $postType, string $type = 'Person'): array
    {
        $posts = get_posts([
            'post_type'      => $postType,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ]);

        return array_map(static fn ($post): array => [
            '@type' => $type,
            'name'  => get_the_title($post),
        ], $posts);
    }

    /**
     * Optional client-authored additions/overrides, top-level merged over the
     * assembled payload. Reads the `schema_additions` option; ignored if blank or
     * not a valid JSON object.
     *
     * @return array<string, mixed>
     */
    protected function additions(): array
    {
        $raw = trim($this->getOption('schema_additions'));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildLocation(): ?array
    {
        $name = $this->getOption('theatre_name');
        if (!$name) {
            return null;
        }

        $location = ['@type' => 'PerformingArtsTheater', 'name' => $name];

        if ($url = $this->getOption('theatre_url')) {
            $location['sameAs'] = $url;
        }

        $address = array_filter([
            'streetAddress'   => $this->getOption('theatre_street'),
            'addressLocality' => $this->getOption('theatre_city'),
            'postalCode'      => $this->getOption('theatre_postal'),
            'addressRegion'   => $this->getOption('theatre_region'),
            'addressCountry'  => $this->getOption('theatre_country'),
        ]);

        if (!empty($address)) {
            $location['address'] = array_merge(['@type' => 'PostalAddress'], $address);
        }

        return $location;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildOffers(): ?array
    {
        $url   = $this->getOption('ticket_url');
        $price = $this->getOption('event_price');

        if (!$url && $price === '') {
            return null;
        }

        $offers = [
            '@type'         => 'Offer',
            'priceCurrency' => 'USD',
            'availability'  => 'https://schema.org/InStock',
            'validFrom'     => current_time('Y-m-d'),
        ];

        if ($url) {
            $offers['url'] = $url;
        }
        if ($price !== '') {
            $offers['price'] = (float) $price;
        }

        return $offers;
    }

    protected function getCanonicalUrl(): string
    {
        if (is_singular()) {
            return (string) get_permalink();
        }

        if (is_archive() && $url = get_post_type_archive_link(get_query_var('post_type') ?: 'post')) {
            return $url;
        }

        return home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
    }

    protected function getOption(string $key, string $default = ''): string
    {
        if (!function_exists('get_field')) {
            return $default;
        }
        $value = get_field($key, 'option');

        return $value !== '' && $value !== null && $value !== false ? (string) $value : $default;
    }
}
