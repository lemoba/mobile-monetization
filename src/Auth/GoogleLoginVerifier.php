<?php

namespace Lemoba\MobileMonetization\Auth;

use Lemoba\MobileMonetization\Exceptions\MobileMonetizationException;

class GoogleLoginVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public function __construct(
        private readonly array $config,
        private readonly ?JwksVerifier $jwksVerifier = null,
        private readonly array $cacheConfig = []
    )
    {
    }

    public function verifyIdToken(string $idToken, ?string $expectedNonce = null): array
    {
        $claims = ($this->jwksVerifier ?? new JwksVerifier($this->cacheConfig))->decode(
            $idToken,
            self::JWKS_URL,
            'auth.google.login.jwks',
            (int) ($this->cacheConfig['jwks_ttl'] ?? 3600)
        );

        if (!in_array($claims->iss ?? null, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new MobileMonetizationException('Google ID token issuer mismatch.', 401);
        }

        $allowedAudiences = $this->config['android_client_ids'] ?? [];
        if ($allowedAudiences && !in_array($claims->aud ?? null, $allowedAudiences, true)) {
            throw new MobileMonetizationException('Google ID token audience mismatch.', 401);
        }

        if ($expectedNonce !== null && ($claims->nonce ?? null) !== $expectedNonce) {
            throw new MobileMonetizationException('Google ID token nonce mismatch.', 401);
        }

        if (empty($claims->sub)) {
            throw new MobileMonetizationException('Google ID token subject is missing.', 401, (array) $claims);
        }

        return [
            'provider' => 'google',
            'provider_user_id' => $claims->sub,
            'email' => $claims->email ?? null,
            'email_verified' => filter_var($claims->email_verified ?? false, FILTER_VALIDATE_BOOL),
            'name' => $claims->name ?? null,
            'picture' => $claims->picture ?? null,
            'claims' => (array) $claims,
        ];
    }
}
