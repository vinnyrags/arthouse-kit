<?php

/**
 * Server-side render for arthouse/newsletter.
 *
 * Emits the form HTML the shared handler in assets/js/newsletter.js binds to
 * (#newsletterForm + #fieldEmail). Same markup ships in the editor via
 * ServerSideRender so authors see what the frontend renders.
 *
 * The context is passed through NewsletterProvider::BLOCK_CONTEXT_FILTER so a
 * subclass can inject the La MaMa opt-in label + consent fineprint (empty by
 * default → the twig renders neither).
 */

use Arthouse\Providers\Newsletter\NewsletterProvider;
use Timber\Timber;

$context = Timber::context();
$context['wrapper_attributes'] = get_block_wrapper_attributes(['class' => 'wp-block-newsletter']);
$context['placeholder']    = $attributes['placeholder']    ?? 'Your email address here';
$context['submit_label']   = $attributes['submitLabel']    ?? 'Sign Up';
$context['required_label'] = $attributes['requiredLabel']  ?? '*Required';
$context['optin_label']    = '';
$context['fineprint']      = '';

$context = apply_filters(NewsletterProvider::BLOCK_CONTEXT_FILTER, $context, $attributes ?? []);

Timber::render(__DIR__ . '/newsletter.twig', $context);
