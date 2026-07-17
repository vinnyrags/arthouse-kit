<?php

/**
 * Server-side render for arthouse/newsletter.
 *
 * Emits the form HTML the shared handler in assets/js/newsletter.js binds to
 * (#newsletterForm + #fieldEmail). Same markup ships in the editor via
 * ServerSideRender so authors see what the frontend renders.
 *
 * The optional partner opt-in checkbox + consent fineprint are CMS-driven (Site
 * Settings → Campaign Monitor): blank = not rendered. They're built here into a
 * single raw slot the twig prints just before </form>, so the kit carries the
 * generic mechanism while all copy (label, list, fineprint) lives in the CMS — no
 * per-show content in code. The slot is an output tag, so an empty value leaves the
 * surrounding whitespace byte-identical to a form with no extra fields.
 */

use Timber\Timber;

$getOption = static function (string $key): string {
    if (!function_exists('get_field')) {
        return '';
    }
    $value = get_field($key, 'option');

    return is_string($value) ? trim($value) : '';
};

$optinLabel = $getOption('campaign_monitor_optin_label');
$fineprint  = $getOption('newsletter_fineprint');

$extra = '';
if ($optinLabel !== '') {
    $extra .= "\n        <label class=\"newsletter-form__optin\">\n"
        . "            <input type=\"checkbox\" name=\"optin\" value=\"yes\" checked />\n"
        . '            <span>' . esc_html($optinLabel) . "</span>\n"
        . '        </label>';
}
if ($fineprint !== '') {
    $extra .= "\n        <p class=\"newsletter-form__fineprint\">" . esc_html($fineprint) . '</p>';
}

$context = Timber::context();
$context['wrapper_attributes'] = get_block_wrapper_attributes(['class' => 'wp-block-newsletter']);
$context['placeholder']    = $attributes['placeholder']    ?? 'Your email address here';
$context['submit_label']   = $attributes['submitLabel']    ?? 'Sign Up';
$context['required_label'] = $attributes['requiredLabel']  ?? '*Required';
$context['extra_fields']   = $extra;

Timber::render(__DIR__ . '/newsletter.twig', $context);
