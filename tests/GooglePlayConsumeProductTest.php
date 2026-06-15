<?php

require __DIR__ . '/../src/Exceptions/MobileMonetizationException.php';
require __DIR__ . '/../src/Support/CacheConfig.php';
require __DIR__ . '/../src/Payments/VerifiedPurchase.php';
require __DIR__ . '/../src/Payments/GooglePlayVerifier.php';

use Lemoba\MobileMonetization\Payments\GooglePlayVerifier;
use Lemoba\MobileMonetization\Payments\VerifiedPurchase;

function assertGoogleConsumeSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

class GooglePlayConsumeProductTestVerifier extends GooglePlayVerifier
{
    public int $consumeCalls = 0;

    public function __construct(private readonly bool $validPurchase)
    {
        parent::__construct([]);
    }

    public function verifyProduct(string $productId, string $purchaseToken): VerifiedPurchase
    {
        return new VerifiedPurchase(
            platform: 'android',
            productId: $productId,
            transactionId: 'order-' . $purchaseToken,
            originalTransactionId: 'order-' . $purchaseToken,
            type: 'consumable',
            valid: $this->validPurchase,
            consumable: true,
        );
    }

    public function consumeProduct(string $productId, string $purchaseToken): void
    {
        $this->consumeCalls++;
    }
}

$validVerifier = new GooglePlayConsumeProductTestVerifier(true);
$validPurchase = $validVerifier->verifyAndConsumeProduct('coins_100', 'token-valid');

assertGoogleConsumeSame(true, $validPurchase->valid, 'Valid Google Play product purchase should be returned.');
assertGoogleConsumeSame(1, $validVerifier->consumeCalls, 'Valid Google Play product purchase should be consumed.');

$invalidVerifier = new GooglePlayConsumeProductTestVerifier(false);
$invalidPurchase = $invalidVerifier->verifyAndConsumeProduct('coins_100', 'token-invalid');

assertGoogleConsumeSame(false, $invalidPurchase->valid, 'Invalid Google Play product purchase should be returned.');
assertGoogleConsumeSame(0, $invalidVerifier->consumeCalls, 'Invalid Google Play product purchase should not be consumed.');
