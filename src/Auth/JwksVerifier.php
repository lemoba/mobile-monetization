<?php

namespace Lemoba\MobileMonetization\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Lemoba\MobileMonetization\Exceptions\MobileMonetizationException;
use Lemoba\MobileMonetization\Support\CacheConfig;
use stdClass;

class JwksVerifier
{
    public function __construct(private readonly array $cacheConfig = [])
    {
    }

    public function decode(string $jwt, string $jwksUrl, string $cacheKey, int $ttlSeconds = 3600): stdClass
    {
        $cache = new CacheConfig($this->cacheConfig);
        $key = $cache->key($cacheKey);
        $ttl = $ttlSeconds ?: $cache->jwksTtl();

        $jwks = $cache->store()->remember($key, $ttl, function () use ($jwksUrl) {
            $response = Http::timeout(10)->get($jwksUrl);

            if (!$response->successful()) {
                throw new MobileMonetizationException('Unable to fetch JWKS.', $response->status(), $response->body());
            }

            return $response->json();
        });

        try {
            return JWT::decode($jwt, JWK::parseKeySet($jwks));
        } catch (\Throwable $e) {
            $cache->store()->forget($key);

            try {
                $fresh = Http::timeout(10)->get($jwksUrl)->throw()->json();
                $cache->store()->put($key, $fresh, $ttl);

                return JWT::decode($jwt, JWK::parseKeySet($fresh));
            } catch (\Throwable) {
                throw new MobileMonetizationException('Invalid identity token.', 401, $e->getMessage());
            }
        }
    }
}
