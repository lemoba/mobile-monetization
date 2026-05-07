<?php

namespace Lemoba\MobileMonetization;

use Lemoba\MobileMonetization\Ads\LevelPlayService;
use Lemoba\MobileMonetization\Auth\AppleLoginVerifier;
use Lemoba\MobileMonetization\Auth\GoogleLoginVerifier;
use Lemoba\MobileMonetization\Payments\AppleIapVerifier;
use Lemoba\MobileMonetization\Payments\GooglePlayVerifier;
use Lemoba\MobileMonetization\Payments\VerifiedPurchase;
use Lemoba\MobileMonetization\Push\FcmMessage;
use Lemoba\MobileMonetization\Push\FirebaseCloudMessaging;

class MobileMonetizationManager
{
    public function __construct(
        public readonly AppleLoginVerifier $appleLogin,
        public readonly GoogleLoginVerifier $googleLogin,
        public readonly AppleIapVerifier $appleIap,
        public readonly GooglePlayVerifier $googlePlay,
        public readonly LevelPlayService $levelPlay,
        public readonly FirebaseCloudMessaging $push,
    ) {
    }

    public function verifyAppleIdentityToken(string $identityToken, ?string $expectedNonce = null): array
    {
        return $this->appleLogin->verifyIdentityToken($identityToken, $expectedNonce);
    }

    public function exchangeAppleAuthorizationCode(string $code): array
    {
        return $this->appleLogin->exchangeAuthorizationCode($code);
    }

    public function verifyGoogleIdToken(string $idToken, ?string $expectedNonce = null): array
    {
        return $this->googleLogin->verifyIdToken($idToken, $expectedNonce);
    }

    public function verifyAppleTransactionId(string $transactionId, ?bool $consumable = null): VerifiedPurchase
    {
        return $this->appleIap->verifyTransactionId($transactionId, $consumable);
    }

    public function verifyAppleSignedTransaction(string $signedTransactionInfo, ?bool $consumable = null): VerifiedPurchase
    {
        return $this->appleIap->verifySignedTransaction($signedTransactionInfo, $consumable);
    }

    public function decodeAppleSignedTransaction(string $signedTransactionInfo): array
    {
        return $this->appleIap->decodeAppleJws($signedTransactionInfo);
    }

    public function verifyGoogleProduct(string $productId, string $purchaseToken): VerifiedPurchase
    {
        return $this->googlePlay->verifyProduct($productId, $purchaseToken);
    }

    public function verifyGoogleSubscription(string $subscriptionId, string $purchaseToken): VerifiedPurchase
    {
        return $this->googlePlay->verifySubscription($subscriptionId, $purchaseToken);
    }

    public function acknowledgeGoogleProduct(string $productId, string $purchaseToken, ?string $developerPayload = null): void
    {
        $this->googlePlay->acknowledgeProduct($productId, $purchaseToken, $developerPayload);
    }

    public function consumeGoogleProduct(string $productId, string $purchaseToken): void
    {
        $this->googlePlay->consumeProduct($productId, $purchaseToken);
    }

    public function verifyLevelPlayRewardCallback($input, bool $dev = false): array
    {
        return $this->levelPlay->verifyRewardCallback($input, $dev);
    }

    public function levelPlayOkResponse(string $eventId): string
    {
        return $this->levelPlay->okResponse($eventId);
    }

    public function sendFcmToToken(
        string $platform,
        string $token,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
        array $options = []
    ): FcmMessage {
        return $this->push->sendToToken($platform, $token, $title, $body, $data, $options);
    }

    public function sendFcmToTopic(
        string $platform,
        string $topic,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
        array $options = []
    ): FcmMessage {
        return $this->push->sendToTopic($platform, $topic, $title, $body, $data, $options);
    }

    public function sendFcm(string $platform, array $message, bool $validateOnly = false): FcmMessage
    {
        return $this->push->send($platform, $message, $validateOnly);
    }

    public function fcmAccessToken(string $platform): string
    {
        return $this->push->accessToken($platform);
    }
}
