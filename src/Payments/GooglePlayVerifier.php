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

    public function verifyProduct(?string $productId = null, ?string $purchaseToken = null): VerifiedPurchase
    {
        [$productId, $purchaseToken] = $this->resolveProductTokenArguments($productId, $purchaseToken);
        $data = $this->getProductPurchase($purchaseToken);
        $purchaseState = $data['purchaseStateContext']['purchaseState'] ?? null;
        $lineItem = $data['productLineItem'][0] ?? [];
        $resolvedProductId = $lineItem['productId'] ?? $productId;

        if ($resolvedProductId === null || $resolvedProductId === '') {
            throw new MobileMonetizationException('Google Play product ID was not returned for this purchase token.');
        }

        return new VerifiedPurchase(
            platform: 'android',
            productId: $resolvedProductId,
            transactionId: $data['orderId'] ?? $purchaseToken,
            originalTransactionId: $data['orderId'] ?? $purchaseToken,
            type: 'consumable',
            valid: $purchaseState === 'PURCHASED',
            consumable: true,
            purchasedAtMs: isset($data['purchaseCompletionTime']) ? strtotime($data['purchaseCompletionTime']) * 1000 : null,
            expiresAtMs: null,
            environment: $data['testPurchaseContext']['fopType'] ?? null,
            raw: $data,
            externalProfileId: $this->productExternalProfileId($data),
        );
    }

    public function verifyAndConsumeProduct(?string $productId = null, ?string $purchaseToken = null): VerifiedPurchase
    {
        [, $resolvedPurchaseToken] = $this->resolveProductTokenArguments($productId, $purchaseToken);
        $purchase = $this->verifyProduct($productId, $purchaseToken);

        if ($purchase->valid) {
            $this->consumeProduct($purchase->productId, $resolvedPurchaseToken);
        }

        return $purchase;
    }

    public function parseOrderNo(string $purchaseToken): array
    {
        try {
            $data = $this->getProductPurchase($purchaseToken);
            $lineItem = $data['productLineItem'][0] ?? [];

            return [
                'order_no' => $this->productExternalProfileId($data),
                'product_id' => $lineItem['productId'] ?? null,
                'type' => 'consumable',
                'transaction_id' => $data['orderId'] ?? $purchaseToken,
                'purchase_token' => $purchaseToken,
                'raw' => $data,
            ];
        } catch (MobileMonetizationException $productException) {
            try {
                $data = $this->getSubscriptionPurchase($purchaseToken);
                $lineItem = $data['lineItems'][0] ?? [];

                return [
                    'order_no' => $this->subscriptionExternalProfileId($data),
                    'product_id' => $lineItem['productId'] ?? null,
                    'type' => 'subscription',
                    'transaction_id' => $data['latestOrderId'] ?? $purchaseToken,
                    'purchase_token' => $purchaseToken,
                    'raw' => $data,
                ];
            } catch (MobileMonetizationException $subscriptionException) {
                throw new MobileMonetizationException('Google Play order number could not be parsed from this purchase token.', 400, [
                    'product_error' => [
                        'message' => $productException->getMessage(),
                        'code' => $productException->getCode(),
                        'context' => $productException->context(),
                    ],
                    'subscription_error' => [
                        'message' => $subscriptionException->getMessage(),
                        'code' => $subscriptionException->getCode(),
                        'context' => $subscriptionException->context(),
                    ],
                ]);
            }
        }
    }

    public function verifySubscription(?string $subscriptionId = null, ?string $purchaseToken = null, ?string $productId = null): VerifiedPurchase
    {
        [$productId, $purchaseToken] = $this->resolveSubscriptionTokenArguments($subscriptionId, $purchaseToken, $productId);
        $data = $this->getSubscriptionPurchase($purchaseToken);
        $lineItem = $data['lineItems'][0] ?? [];
        $expiry = $lineItem['expiryTime'] ?? null;
        $state = $data['subscriptionState'] ?? null;

        return new VerifiedPurchase(
            platform: 'android',
            productId: $lineItem['productId'] ?? $productId,
            transactionId: $data['latestOrderId'] ?? $purchaseToken,
            originalTransactionId: $data['linkedPurchaseToken'] ?? $data['latestOrderId'] ?? $purchaseToken,
            type: 'subscription',
            valid: in_array($state, ['SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD'], true),
            consumable: false,
            purchasedAtMs: isset($data['startTime']) ? strtotime($data['startTime']) * 1000 : null,
            expiresAtMs: $expiry ? strtotime($expiry) * 1000 : null,
            environment: $data['testPurchase']['testPurchase'] ?? null,
            raw: $data,
            externalProfileId: $this->subscriptionExternalProfileId($data),
        );
    }

    public function verifySubscriptionOffer(
        ?string $subscriptionId = null,
        ?string $purchaseToken = null,
        ?string $expectedBasePlanId = null,
        ?string $expectedOfferId = null,
        ?string $productId = null
    ): array {
        $purchase = $this->verifySubscription(subscriptionId: $subscriptionId, purchaseToken: $purchaseToken, productId: $productId);
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

    private function getProductPurchase(string $purchaseToken): array
    {
        $packageName = $this->packageName();
        $path = sprintf(
            '%s/%s/purchases/productsv2/tokens/%s',
            self::API_ROOT,
            rawurlencode($packageName),
            rawurlencode($purchaseToken)
        );

        return $this->get($path);
    }

    private function getSubscriptionPurchase(string $purchaseToken): array
    {
        $packageName = $this->packageName();
        $path = sprintf(
            '%s/%s/purchases/subscriptionsv2/tokens/%s',
            self::API_ROOT,
            rawurlencode($packageName),
            rawurlencode($purchaseToken)
        );

        return $this->get($path);
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

    private function resolveProductTokenArguments(?string $productId, ?string $purchaseToken): array
    {
        if ($productId === null && $purchaseToken === null) {
            throw new MobileMonetizationException('Google Play purchase token is required.');
        }

        if ($purchaseToken === null) {
            return [null, $productId];
        }

        return [$productId, $purchaseToken];
    }

    private function resolveSubscriptionTokenArguments(?string $subscriptionId, ?string $purchaseToken, ?string $productId): array
    {
        $resolvedProductId = $productId ?? $subscriptionId;

        if ($resolvedProductId === null && $purchaseToken === null) {
            throw new MobileMonetizationException('Google Play purchase token is required.');
        }

        if ($purchaseToken === null) {
            return [null, $resolvedProductId];
        }

        return [$resolvedProductId, $purchaseToken];
    }

    private function productExternalProfileId(array $data): ?string
    {
        return $data['obfuscatedExternalProfileId'] ?? null;
    }

    private function subscriptionExternalProfileId(array $data): ?string
    {
        $identifiers = $data['externalAccountIdentifiers'] ?? [];

        return $identifiers['obfuscatedExternalProfileId']
            ?? $data['obfuscatedExternalProfileId']
            ?? null;
    }
}
