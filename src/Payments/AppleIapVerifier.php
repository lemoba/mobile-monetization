<?php

namespace Lemoba\MobileMonetization\Payments;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use Lemoba\MobileMonetization\Exceptions\MobileMonetizationException;
use Lemoba\MobileMonetization\Support\CacheConfig;

class AppleIapVerifier
{
    private const PRODUCTION_URL = 'https://api.storekit.itunes.apple.com';
    private const SANDBOX_URL = 'https://api.storekit-sandbox.itunes.apple.com';

    public function __construct(private readonly array $config, private readonly array $cacheConfig = [])
    {
    }

    public function verifyTransactionId(string $transactionId, ?bool $consumable = null): VerifiedPurchase
    {
        $response = $this->request('GET', "/inApps/v1/transactions/{$transactionId}");
        $signedTransactionInfo = $response['signedTransactionInfo'] ?? null;

        if (!$signedTransactionInfo) {
            throw new MobileMonetizationException('Apple transaction response is missing signedTransactionInfo.', 422, $response);
        }

        return $this->verifySignedTransaction($signedTransactionInfo, $consumable);
    }

    public function verifySignedTransaction(string $signedTransactionInfo, ?bool $consumable = null): VerifiedPurchase
    {
        $claims = $this->decodeAppleJws($signedTransactionInfo);
        $bundleId = $this->config['bundle_id'] ?? null;

        if ($bundleId && ($claims['bundleId'] ?? null) !== $bundleId) {
            throw new MobileMonetizationException('Apple transaction bundle ID mismatch.', 401, $claims);
        }

        $productType = $claims['type'] ?? 'Unknown';
        $isSubscription = in_array($productType, ['Auto-Renewable Subscription', 'Non-Renewing Subscription'], true);

        return new VerifiedPurchase(
            platform: 'ios',
            productId: (string) ($claims['productId'] ?? ''),
            transactionId: (string) ($claims['transactionId'] ?? ''),
            originalTransactionId: (string) ($claims['originalTransactionId'] ?? ($claims['transactionId'] ?? '')),
            type: $isSubscription ? 'subscription' : 'consumable',
            valid: empty($claims['revocationDate']),
            consumable: $consumable ?? !$isSubscription,
            purchasedAtMs: isset($claims['purchaseDate']) ? (int) $claims['purchaseDate'] : null,
            expiresAtMs: isset($claims['expiresDate']) ? (int) $claims['expiresDate'] : null,
            environment: $claims['environment'] ?? null,
            raw: $claims,
        );
    }

    public function decodeAppleJws(string $jws): array
    {
        $segments = explode('.', $jws);
        if (count($segments) !== 3) {
            throw new MobileMonetizationException('Invalid Apple JWS format.', 422);
        }

        $header = json_decode(JWT::urlsafeB64Decode($segments[0]), true);
        $certificate = $header['x5c'][0] ?? null;
        if (!$certificate) {
            throw new MobileMonetizationException('Apple JWS certificate chain is missing.', 422);
        }

        $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($certificate, 64, "\n") . "-----END CERTIFICATE-----\n";
        $publicKey = openssl_pkey_get_public($pem);
        if (!$publicKey) {
            throw new MobileMonetizationException('Unable to read Apple JWS public key.', 422);
        }

        try {
            return (array) JWT::decode($jws, new Key($publicKey, 'ES256'));
        } catch (\Throwable $e) {
            throw new MobileMonetizationException('Apple signed transaction verification failed.', 401, $e->getMessage());
        }
    }

    private function request(string $method, string $path): array
    {
        $response = Http::withToken($this->bearerToken())
            ->acceptJson()
            ->timeout(15)
            ->send($method, $this->baseUrl() . $path);

        if ($response->status() === 404 && ($this->config['environment'] ?? 'production') === 'production') {
            $response = Http::withToken($this->bearerToken())
                ->acceptJson()
                ->timeout(15)
                ->send($method, self::SANDBOX_URL . $path);
        }

        if (!$response->successful()) {
            throw new MobileMonetizationException('Apple App Store Server API request failed.', $response->status(), $response->json() ?: $response->body());
        }

        return $response->json();
    }

    private function bearerToken(): string
    {
        foreach (['issuer_id', 'key_id', 'bundle_id'] as $key) {
            if (empty($this->config[$key])) {
                throw new MobileMonetizationException('Apple App Store Server API config is incomplete.');
            }
        }

        $cache = new CacheConfig($this->cacheConfig);
        $cacheKey = $cache->key('payments.apple_app_store.bearer_token.' . sha1($this->config['issuer_id'] . '|' . $this->config['key_id'] . '|' . $this->config['bundle_id']));

        return $cache->store()->remember($cacheKey, min($cache->oauthTokenTtl(), 3000), function () {
            $now = time();

            return JWT::encode([
                'iss' => $this->config['issuer_id'],
                'iat' => $now,
                'exp' => $now + 3000,
                'aud' => 'appstoreconnect-v1',
                'bid' => $this->config['bundle_id'],
            ], $this->privateKey(), 'ES256', $this->config['key_id']);
        });
    }

    private function baseUrl(): string
    {
        return ($this->config['environment'] ?? 'production') === 'sandbox' ? self::SANDBOX_URL : self::PRODUCTION_URL;
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
