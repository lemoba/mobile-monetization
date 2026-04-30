<?php

namespace Lemoba\MobileMonetization\Auth;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Lemoba\MobileMonetization\Exceptions\MobileMonetizationException;

class AppleLoginVerifier
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';
    private const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    public function __construct(
        private readonly array $config,
        private readonly ?JwksVerifier $jwksVerifier = null,
        private readonly array $cacheConfig = []
    )
    {
    }

    public function verifyIdentityToken(string $identityToken, ?string $expectedNonce = null): array
    {
        $claims = ($this->jwksVerifier ?? new JwksVerifier($this->cacheConfig))->decode(
            $identityToken,
            self::JWKS_URL,
            'auth.apple.login.jwks',
            (int) ($this->cacheConfig['jwks_ttl'] ?? 3600)
        );

        $clientId = $this->config['client_id'] ?: $this->config['bundle_id'];
        if ($clientId && ($claims->aud ?? null) !== $clientId) {
            throw new MobileMonetizationException('Apple identity token audience mismatch.', 401);
        }

        if (($claims->iss ?? null) !== 'https://appleid.apple.com') {
            throw new MobileMonetizationException('Apple identity token issuer mismatch.', 401);
        }

        if ($expectedNonce !== null && ($claims->nonce ?? null) !== $expectedNonce) {
            throw new MobileMonetizationException('Apple identity token nonce mismatch.', 401);
        }

        if (empty($claims->sub)) {
            throw new MobileMonetizationException('Apple identity token subject is missing.', 401, (array) $claims);
        }

        return [
            'provider' => 'apple',
            'provider_user_id' => $claims->sub,
            'email' => $claims->email ?? '',
            'email_verified' => filter_var($claims->email_verified ?? false, FILTER_VALIDATE_BOOL),
            'claims' => (array) $claims,
        ];
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        $clientId = $this->config['client_id'] ?: $this->config['bundle_id'];
        if (!$clientId) {
            throw new MobileMonetizationException('APPLE_CLIENT_ID or APPLE_BUNDLE_ID is required.');
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'client_id' => $clientId,
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new MobileMonetizationException('Apple authorization code exchange failed.', $response->status(), $response->json() ?: $response->body());
        }

        $payload = $response->json();
        $payload['identity'] = isset($payload['id_token']) ? $this->verifyIdentityToken($payload['id_token']) : null;

        return $payload;
    }

    private function clientSecret(): string
    {
        foreach (['team_id', 'key_id'] as $key) {
            if (empty($this->config[$key])) {
                throw new MobileMonetizationException('Apple client secret config is incomplete.');
            }
        }

        return JWT::encode([
            'iss' => $this->config['team_id'],
            'iat' => time(),
            'exp' => time() + 86400 * 180,
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->config['client_id'] ?: $this->config['bundle_id'],
        ], $this->privateKey(), 'ES256', $this->config['key_id']);
    }

    private function privateKey(): string
    {
        if (!empty($this->config['private_key'])) {
            return str_replace('\\n', "\n", $this->config['private_key']);
        }

        if (!empty($this->config['private_key_path']) && is_readable($this->config['private_key_path'])) {
            return file_get_contents($this->config['private_key_path']);
        }

        throw new MobileMonetizationException('Apple private key is not configured.');
    }
}
