<?php

namespace Lemoba\MobileMonetization\Payments;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Lemoba\MobileMonetization\Exceptions\MobileMonetizationException;
use Lemoba\MobileMonetization\Support\CacheConfig;

class GooglePlayVerifier
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_ROOT = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications';
    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    public function __construct(private readonly array $config, private readonly array $cacheConfig = [])
    {
    }

    public function verifyProduct(string $productId, string $purchaseToken): VerifiedPurchase
    {
        $packageName = $this->packageName();
        $path = sprintf(
            '%s/%s/purchases/productsv2/tokens/%s',
            self::API_ROOT,
            rawurlencode($packageName),
            rawurlencode($purchaseToken)
        );

        $data = $this->get($path);
        $purchaseState = $data['purchaseStateContext']['purchaseState'] ?? null;
        $lineItem = $data['productLineItem'][0] ?? [];

        return new VerifiedPurchase(
            platform: 'android',
            productId: $lineItem['productId'] ?? $productId,
            transactionId: $data['orderId'] ?? $purchaseToken,
            originalTransactionId: $data['orderId'] ?? $purchaseToken,
            type: 'consumable',
            valid: $purchaseState === 'PURCHASED',
            consumable: true,
            purchasedAtMs: isset($data['purchaseCompletionTime']) ? strtotime($data['purchaseCompletionTime']) * 1000 : null,
            expiresAtMs: null,
            environment: $data['testPurchaseContext']['fopType'] ?? null,
            raw: $data,
        );
    }

    public function verifySubscription(string $subscriptionId, string $purchaseToken): VerifiedPurchase
    {
        $packageName = $this->packageName();
        $path = sprintf(
            '%s/%s/purchases/subscriptionsv2/tokens/%s',
            self::API_ROOT,
            rawurlencode($packageName),
            rawurlencode($purchaseToken)
        );

        $data = $this->get($path);
        $lineItem = $data['lineItems'][0] ?? [];
        $expiry = $lineItem['expiryTime'] ?? null;
        $state = $data['subscriptionState'] ?? null;

        return new VerifiedPurchase(
            platform: 'android',
            productId: $lineItem['productId'] ?? $subscriptionId,
            transactionId: $data['latestOrderId'] ?? $purchaseToken,
            originalTransactionId: $data['linkedPurchaseToken'] ?? $data['latestOrderId'] ?? $purchaseToken,
            type: 'subscription',
            valid: in_array($state, ['SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD'], true),
            consumable: false,
            purchasedAtMs: isset($data['startTime']) ? strtotime($data['startTime']) * 1000 : null,
            expiresAtMs: $expiry ? strtotime($expiry) * 1000 : null,
            environment: $data['testPurchase']['testPurchase'] ?? null,
            raw: $data,
        );
    }

    public function verifySubscriptionOffer(
        string $subscriptionId,
        string $purchaseToken,
        ?string $expectedBasePlanId = null,
        ?string $expectedOfferId = null
    ): array {
        $purchase = $this->verifySubscription($subscriptionId, $purchaseToken);
        $lineItem = $purchase->raw['lineItems'][0] ?? [];
        $offerDetails = $lineItem['offerDetails'] ?? [];
        $basePlanId = $offerDetails['basePlanId'] ?? null;
        $offerId = $offerDetails['offerId'] ?? null;

        if ($expectedBasePlanId !== null && $basePlanId !== $expectedBasePlanId) {
            throw new MobileMonetizationException('Google Play subscription base plan mismatch.', 401, [
                'expected_base_plan_id' => $expectedBasePlanId,
                'actual_base_plan_id' => $basePlanId,
                'purchase' => $purchase->toArray(),
            ]);
        }

        if ($expectedOfferId !== null && $offerId !== $expectedOfferId) {
            throw new MobileMonetizationException('Google Play subscription offer mismatch.', 401, [
                'expected_offer_id' => $expectedOfferId,
                'actual_offer_id' => $offerId,
                'purchase' => $purchase->toArray(),
            ]);
        }

        return [
            'purchase' => $purchase,
            'base_plan_id' => $basePlanId,
            'offer_id' => $offerId,
            'offer_tags' => $offerDetails['offerTags'] ?? [],
            'pricing_phase' => $offerDetails['offerPhase'] ?? null,
            'raw_offer_details' => $offerDetails,
        ];
    }

    public function googleSubscriptionOfferTokenNotice(): array
    {
        return [
            'server_signature_required' => false,
            'message' => 'Google Play subscription offers use the offerToken returned by Play Billing ProductDetails on the client; the server verifies the resulting purchase token and offerDetails.',
        ];
    }

    public function acknowledgeProduct(string $productId, string $purchaseToken, ?string $developerPayload = null): void
    {
        $packageName = $this->packageName();
        $path = sprintf(
            '%s/%s/purchases/products/%s/tokens/%s:acknowledge',
            self::API_ROOT,
            rawurlencode($packageName),
            rawurlencode($productId),
            rawurlencode($purchaseToken)
        );

        $this->post($path, array_filter(['developerPayload' => $developerPayload]));
    }

    public function consumeProduct(string $productId, string $purchaseToken): void
    {
        $packageName = $this->packageName();
        $path = sprintf(
            '%s/%s/purchases/products/%s/tokens/%s:consume',
            self::API_ROOT,
            rawurlencode($packageName),
            rawurlencode($productId),
            rawurlencode($purchaseToken)
        );

        $this->post($path, []);
    }

    private function get(string $url): array
    {
        $response = Http::withToken($this->accessToken())->acceptJson()->timeout(15)->get($url);

        if (!$response->successful()) {
            throw new MobileMonetizationException('Google Play Developer API request failed.', $response->status(), $response->json() ?: $response->body());
        }

        return $response->json();
    }

    private function post(string $url, array $payload): void
    {
        $response = Http::withToken($this->accessToken())->acceptJson()->timeout(15)->post($url, $payload);

        if (!$response->successful() && $response->status() !== 409) {
            throw new MobileMonetizationException('Google Play Developer API write request failed.', $response->status(), $response->json() ?: $response->body());
        }
    }

    private function accessToken(): string
    {
        $serviceAccount = $this->serviceAccount();
        $cache = new CacheConfig($this->cacheConfig);
        $cacheKey = $cache->key('payments.google_play.access_token.' . sha1($serviceAccount['client_email']));

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
                throw new MobileMonetizationException('Google service account OAuth failed.', $response->status(), $response->json() ?: $response->body());
            }

            return $response->json('access_token');
        });
    }

    private function serviceAccount(): array
    {
        $json = $this->config['service_account_json'] ?? null;
        if (!$json && !empty($this->config['service_account_json_path']) && is_readable($this->config['service_account_json_path'])) {
            $json = file_get_contents($this->config['service_account_json_path']);
        }

        $data = $json ? json_decode($json, true) : null;
        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new MobileMonetizationException('Google Play service account JSON is not configured.');
        }

        return $data;
    }

    private function packageName(): string
    {
        if (empty($this->config['package_name'])) {
            throw new MobileMonetizationException('GOOGLE_PLAY_PACKAGE_NAME is required.');
        }

        return $this->config['package_name'];
    }
}
