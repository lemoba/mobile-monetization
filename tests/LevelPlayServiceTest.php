<?php

require __DIR__ . '/../src/Exceptions/MobileMonetizationException.php';
require __DIR__ . '/../src/Ads/LevelPlayService.php';

use Lemoba\MobileMonetization\Ads\LevelPlayService;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$secret = 'levelplay-secret';
$params = [
    'appKey' => 'levelplay-app-key',
    'userId' => 'user-123',
    'transId' => 'tx-456',
    'rewardName' => 'coins',
    'rewardAmount' => '10',
    'timestamp' => '1777462800',
    'adUnit' => 'rewarded_video',
    'placement' => 'daily_bonus',
    'network' => 'UnityAds',
    'customParameters' => 'item_id%3Dsword_001%26level%3D5%26order_id%3DORD-20260429-001',
];
$params['signature'] = hash_hmac(
    'sha256',
    $params['appKey']
        . $params['userId']
        . $params['transId']
        . $params['rewardName']
        . $params['rewardAmount']
        . $params['timestamp'],
    $secret
);

$service = new LevelPlayService([
    'key' => 'levelplay-app-key',
    'secret' => $secret,
]);

$reward = $service->verifyRewardCallback($params);

assertSameValue('tx-456', $reward['event_id'], 'transId should be returned as event_id.');
assertSameValue('user-123', $reward['user_id'], 'userId should be returned as user_id.');
assertSameValue('coins', $reward['reward_item'], 'rewardName should be returned as reward_item.');
assertSameValue(10, $reward['reward_amount'], 'rewardAmount should be returned as reward_amount.');
assertSameValue('daily_bonus', $reward['placement'], 'placement should be returned.');
assertSameValue('UnityAds', $reward['network'], 'network should be returned.');
assertSameValue([
    'item_id' => 'sword_001',
    'level' => '5',
    'order_id' => 'ORD-20260429-001',
], $reward['custom_parameters'], 'customParameters should be parsed into custom_parameters.');
assertSameValue('ORD-20260429-001', $reward['order_id'], 'order_id should be exposed from customParameters.');

