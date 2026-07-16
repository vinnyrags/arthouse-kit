<?php

declare(strict_types=1);

namespace Arthouse\Providers\Newsletter\Endpoints;

use Mythus\Support\Rest\Endpoint;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Subscribe an email to the site's Campaign Monitor list.
 *
 * Registered by NewsletterProvider at /wp-json/theme/v1/newsletter/subscribe.
 * Credentials come from the CMS (Site Settings → Campaign Monitor). The call goes
 * out via wp_remote_post to CM's v3.3 API — no createsend-php dependency.
 *
 * The endpoint is generic across every site: it always accepts an optional
 * `lamama_optin` flag, and routes a **best-effort** second subscribe to the
 * La MaMa list only when both the flag is set and a `campaign_monitor_lamama_list_id`
 * option is configured. Sites without that option (the common case) simply never
 * trigger it. A La MaMa failure never fails the primary signup.
 *
 * Response is a plain 200 with { ok, status }; the frontend only reads `ok`.
 */
class NewsletterSubscribeEndpoint extends Endpoint
{
    private const CM_ENDPOINT = 'https://api.createsend.com/api/v3.3/subscribers/%s.json';

    public function getRoute(): string
    {
        return '/newsletter/subscribe';
    }

    public function getMethods(): string
    {
        return 'POST';
    }

    /**
     * Public endpoint — anyone can submit an email. CM does its own rate limiting
     * and duplicate handling; inputs are sanitized + strictly validated below.
     */
    public function getPermission(WP_REST_Request $request): bool
    {
        return true;
    }

    public function getArgs(): array
    {
        return [
            'email' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => static fn ($value): bool => is_email((string) $value) !== false,
            ],
            'lamama_optin' => [
                'required'          => false,
                'type'              => 'boolean',
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response
    {
        $apiKey = $this->getOption('campaign_monitor_api_key');
        $listId = $this->getOption('campaign_monitor_list_id');

        if ($apiKey === '' || $listId === '') {
            return new WP_REST_Response(['ok' => false, 'reason' => 'unconfigured'], 200);
        }

        $email = (string) $request->get_param('email');

        // Primary list — success is measured against this.
        $code = $this->subscribeToList($apiKey, $listId, $email);

        // La MaMa opt-in — best-effort second subscribe to a separate list in the
        // same CM account. A failure here must NOT fail the primary signup, so its
        // result is not surfaced. Inert unless the site configures the list.
        if ((bool) $request->get_param('lamama_optin')) {
            $lamamaListId = $this->getOption('campaign_monitor_lamama_list_id');
            if ($lamamaListId !== '') {
                $this->subscribeToList($apiKey, $lamamaListId, $email);
            }
        }

        return new WP_REST_Response(['ok' => $code === 201, 'status' => $code], 200);
    }

    /**
     * Subscribe an address to a single Campaign Monitor list. Returns the HTTP
     * status code (201 on success), or 0 on a transport error.
     */
    private function subscribeToList(string $apiKey, string $listId, string $email): int
    {
        $response = wp_remote_post(
            sprintf(self::CM_ENDPOINT, $listId),
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($apiKey . ':x'),
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'EmailAddress'   => $email,
                    'ConsentToTrack' => 'Yes',
                    'Resubscribe'    => true,
                ]),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return 0;
        }

        return (int) wp_remote_retrieve_response_code($response);
    }

    /**
     * Read an ACF Site Option as a trimmed string. Returns '' for missing,
     * non-string, or whitespace-only values so callers can compare with ===.
     */
    private function getOption(string $key): string
    {
        if (!function_exists('get_field')) {
            return '';
        }

        $value = get_field($key, 'option');

        return is_string($value) ? trim($value) : '';
    }
}
