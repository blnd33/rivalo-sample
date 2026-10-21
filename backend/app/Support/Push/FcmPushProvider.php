<?php

namespace App\Support\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging (HTTP v1). FCM also delivers to iPhones through APNs once the Apple key
 * is uploaded in the Firebase console.
 *
 * Server-only configuration (never in the app): FCM_PROJECT_ID and FCM_CREDENTIALS_PATH pointing to
 * the service-account JSON on the server.
 */
class FcmPushProvider implements PushProvider
{
    public function __construct(private readonly string $projectId, private readonly string $credentialsPath) {}

    public function send(array $tokens, string $title, ?string $body, array $data = []): array
    {
        $accessToken = $this->accessToken();
        $invalid = [];

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => array_filter(['title' => $title, 'body' => $body]),
                        'data' => array_map('strval', $data),
                        'android' => ['priority' => 'HIGH'],
                        'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                    ],
                ]);

            if ($response->successful()) {
                continue;
            }
            $status = $response->json('error.status');
            if ($response->status() === 404 || in_array($status, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                $invalid[] = $token;
            } else {
                Log::warning('push.fcm_failed', ['status' => $response->status(), 'error' => $status]);
            }
        }

        return $invalid;
    }

    /** OAuth access token from the service account (JWT bearer grant), cached for 50 minutes. */
    private function accessToken(): string
    {
        return Cache::remember('fcm.access_token', now()->addMinutes(50), function () {
            if (! is_readable($this->credentialsPath)) {
                throw new RuntimeException('FCM credentials file is not readable.');
            }
            $sa = json_decode((string) file_get_contents($this->credentialsPath), true, flags: JSON_THROW_ON_ERROR);
            $now = time();
            $segments = [
                $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->b64(json_encode([
                    'iss' => $sa['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];
            $signature = '';
            if (! openssl_sign(implode('.', $segments), $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the FCM token request.');
            }
            $segments[] = $this->b64($signature);

            return Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');
        });
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
