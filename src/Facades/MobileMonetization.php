<?php

namespace Lemoba\MobileMonetization\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array verifyAppleIdentityToken(string $identityToken, ?string $expectedNonce = null)
 * @method static array exchangeAppleAuthorizationCode(string $code)
 * @method static array verifyGoogleIdToken(string $idToken, ?string $expectedNonce = null)
 * @method static \Lemoba\MobileMonetization\Payments\VerifiedPurchase verifyAppleTransactionId(string $transactionId, ?bool $consumable = null)
 * @method static \Lemoba\MobileMonetization\Payments\VerifiedPurchase verifyAppleSignedTransaction(string $signedTransactionInfo, ?bool $consumable = null)
 * @method static array decodeAppleSignedTransaction(string $signedTransactionInfo)
 * @method static \Lemoba\MobileMonetization\Payments\VerifiedPurchase verifyGoogleProduct(string $productId, string $purchaseToken)
 * @method static \Lemoba\MobileMonetization\Payments\VerifiedPurchase verifyGoogleSubscription(string $subscriptionId, string $purchaseToken)
 * @method static void acknowledgeGoogleProduct(string $productId, string $purchaseToken, ?string $developerPayload = null)
 * @method static void consumeGoogleProduct(string $productId, string $purchaseToken)
 * @method static array verifyLevelPlayRewardCallback(\Illuminate\Http\Request|array $input)
 * @method static string levelPlayOkResponse(string $eventId)
 * @method static \Lemoba\MobileMonetization\Push\FcmMessage sendFcmToToken(string $platform, string $token, ?string $title = null, ?string $body = null, array $data = [], array $options = [])
 * @method static \Lemoba\MobileMonetization\Push\FcmMessage sendFcmToTopic(string $platform, string $topic, ?string $title = null, ?string $body = null, array $data = [], array $options = [])
 * @method static \Lemoba\MobileMonetization\Push\FcmMessage sendFcm(string $platform, array $message, bool $validateOnly = false)
 * @method static string fcmAccessToken(string $platform)
 *
 * @see \Lemoba\MobileMonetization\MobileMonetizationManager
 */
class MobileMonetization extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mobile-monetization';
    }
}
