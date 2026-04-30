<?php

namespace Lemoba\MobileMonetization\Push;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Lemoba\MobileMonetization\Exceptions\MobileMonetizationException;
use Lemoba\MobileMonetization\Support\CacheConfig;

class FirebaseCloudMessaging
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const FCM_ROOT = 'https://fcm.googleapis.com/v1/projects';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function __construct(private readonly array $config, private readonly array $cacheConfig = [])
    {
    }

    public function sendToToken(
        string $platform,
        string $token,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
        array $options = []
    ): FcmMessage {
        return $this->send($platform, array_filter([
            'token' => $token,
            'notification' => $this->notification($title, $body),
            'data' => $this->stringData($data),
            'android' => $options['android'] ?? null,
            'apns' => $options['apns'] ?? null,
            'webpush' => $options['webpush'] ?? null,
            'fcm_options' => $options['fcm_options'] ?? null,
        ], fn ($value) => $value !== null && $value !== []));
    }

    public function sendToTopic(
        string $platform,
        string $topic,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
        array $options = []
    ): FcmMessage {
        return $this->send($platform, array_filter([
            'topic' => $topic,
            'notification' => $this->notification($title, $body),
            'data' => $this->stringData($data),
            'android' => $options['android'] ?? null,
            'apns' => $options['apns'] ?? null,
            'webpush' => $options['webpush'] ?? null,
            'fcm_options' => $options['fcm_options'] ?? null,
        ], fn ($value) => $value !== null && $value !== []));
    }

    public function send(string $platform, array $message, bool $validateOnly = false): FcmMessage
    {
        $platform = $this->normalizePlatform($platform);
        $projectId = $this->projectId($platform);

        $response = Http::withToken($this->accessToken($platform))
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->post(self::FCM_ROOT . '/' . rawurlencode($projectId) . '/messages:send', [
                'validate_only' => $validateOnly,
                'message' => $message,
            ]);

        if (!$response->successful()) {
            throw new MobileMonetizationException('Firebase Cloud Messaging request failed.', $response->status(), $response->json() ?: $response->body());
        }

        return new FcmMessage($platform, $response->json());
    }

    public function accessToken(string $platform): string
    {
        $platform = $this->normalizePlatform($platform);
        $serviceAccount = $this->serviceAccount($platform);
        $cache = new CacheConfig($this->cacheConfig);
        $cacheKey = $cache->key('push.fcm.access_token.' . $platform . '.' . sha1($serviceAccount['client_email']));

        return $cache->store()->remember($cacheKey, $cache->oauthTokenTtl(), function () use ($serviceAccount) {
            $now = time();
            $assertion = JWT::encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $serviceAccount['private_key'], 'RS256');

            $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (!$response->successful()) {
                throw new MobileMonetizationException('Firebase service account OAuth failed.', $response->status(), $response->json() ?: $response->body());
            }

            return $response->json('access_token');
        });
    }

    private function serviceAccount(string $platform): array
    {
        $platformConfig = $this->platformConfig($platform);
        $json = $platformConfig['service_account_json'] ?? null;

        if (!$json && !empty($platformConfig['service_account_json_path']) && is_readable($platformConfig['service_account_json_path'])) {
            $json = file_get_contents($platformConfig['service_account_json_path']);
        }

        $data = $json ? json_decode($json, true) : null;
        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new MobileMonetizationException("FCM {$platform} service account JSON is not configured.");
        }

        return $data;
    }

    private function projectId(string $platform): string
    {
        $platformConfig = $this->platformConfig($platform);
        $projectId = $platformConfig['project_id'] ?? null;

        if (!$projectId) {
            $serviceAccount = $this->serviceAccount($platform);
            $projectId = $serviceAccount['project_id'] ?? null;
        }

        if (!$projectId) {
            throw new MobileMonetizationException("FCM {$platform} project ID is not configured.");
        }

        return $projectId;
    }

    private function platformConfig(string $platform): array
    {
        $platform = $this->normalizePlatform($platform);
        $platformConfig = $this->config[$platform] ?? [];

        if (!is_array($platformConfig)) {
            throw new MobileMonetizationException("FCM {$platform} config is invalid.");
        }

        return $platformConfig;
    }

    private function normalizePlatform(?string $platform): string
    {
        $platform = strtolower((string) ($platform ?: $this->config['default_platform'] ?? 'android'));

        return match ($platform) {
            'ios', 'apple' => 'ios',
            'android', 'google' => 'android',
            default => throw new MobileMonetizationException('FCM platform must be android or ios.'),
        };
    }

    private function notification(?string $title, ?string $body): ?array
    {
        return ($title === null && $body === null) ? null : array_filter([
            'title' => $title,
            'body' => $body,
        ], fn ($value) => $value !== null);
    }

    private function stringData(array $data): array
    {
        return array_map(
            fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            $data
        );
    }
}
