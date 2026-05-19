<?php

require __DIR__ . '/../src/Exceptions/MobileMonetizationException.php';
require __DIR__ . '/../src/Payments/AppleIapVerifier.php';

use Lemoba\MobileMonetization\Payments\AppleIapVerifier;

function assertAppleOfferSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertAppleOfferTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$privateKey = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
]);

if (!$privateKey || !openssl_pkey_export($privateKey, $privateKeyPem)) {
    fwrite(STDERR, 'Unable to generate test EC private key.' . PHP_EOL);
    exit(1);
}

$verifier = new AppleIapVerifier([
    'bundle_id' => 'com.example.app',
    'key_id' => 'APPSTOREKEY',
    'private_key' => $privateKeyPem,
    'promotional_offer_key_id' => 'PROMO12345',
    'promotional_offer_private_key' => $privateKeyPem,
]);

$nonce = '47f8a4b5-5957-4d56-bf0f-c7416f33c701';
$timestamp = 1714567890123;
$offer = $verifier->promotionalOfferSignature(
    productIdentifier: 'vip_month',
    subscriptionOfferId: 'intro_month_50',
    appAccountToken: 'User-ABC',
    nonce: $nonce,
    timestamp: $timestamp,
);

assertAppleOfferSame('PROMO12345', $offer['keyIdentifier'], 'Promotional offer keyIdentifier should use the dedicated config value.');
assertAppleOfferSame($nonce, $offer['nonce'], 'Promotional offer nonce should be returned unchanged.');
assertAppleOfferSame($timestamp, $offer['timestamp'], 'Promotional offer timestamp should be returned unchanged.');

$separator = json_decode('"\u2063"');
$payload = implode($separator, [
    'com.example.app',
    'PROMO12345',
    'vip_month',
    'intro_month_50',
    'user-abc',
    strtolower($nonce),
    (string) $timestamp,
]);

$details = openssl_pkey_get_details($privateKey);
$signature = base64_decode($offer['signature'], true);
assertAppleOfferTrue($signature !== false, 'Promotional offer signature should be valid base64.');
assertAppleOfferSame(1, openssl_verify($payload, $signature, $details['key'], OPENSSL_ALGO_SHA256), 'Promotional offer signature should verify against the Apple payload format.');
