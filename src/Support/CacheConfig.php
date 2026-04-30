<?php

namespace Lemoba\MobileMonetization\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class CacheConfig
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function store(): Repository
    {
        $store = $this->config['store'] ?? null;

        return $store ? Cache::store($store) : Cache::store();
    }

    public function key(string $name): string
    {
        $prefix = trim((string) ($this->config['key_prefix'] ?? 'mobile_monetization'), '.:');

        return $prefix . '.' . ltrim($name, '.');
    }

    public function jwksTtl(): int
    {
        return (int) ($this->config['jwks_ttl'] ?? 3600);
    }

    public function oauthTokenTtl(): int
    {
        return (int) ($this->config['oauth_token_ttl'] ?? 3300);
    }
}
