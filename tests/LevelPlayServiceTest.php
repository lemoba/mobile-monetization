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
    'appUserId' => '908871522',
    'country' => 'SG',
    'dynamicUserId' => '890366592747472530',
    'eventId' => '26abYb3da107f192380eY0',
    'publisherSubId' => '0',
    'rewards' => '10',
    'timestamp' => '202604300647',
];
$params['signature'] = md5($params['timestamp'] . $params['eventId'] . $params['appUserId'] . $params['rewards'] . $secret);

$service = new LevelPlayService([
    'secret' => $secret,
]);

$reward = $service->verifyRewardCallback($params);

assertSameValue('26abYb3da107f192380eY0', $reward['event_id'], 'eventId should be returned as event_id.');
assertSameValue('908871522', $reward['user_id'], 'appUserId should be returned as user_id.');
assertSameValue('890366592747472530', $reward['dynamic_user_id'], 'dynamicUserId should be returned.');
assertSameValue('coins', $reward['reward_item'], 'Default reward item should be returned.');
assertSameValue(10, $reward['reward_amount'], 'rewards should be returned as reward_amount.');
assertSameValue('10', $reward['rewards'], 'Raw rewards should be returned.');
assertSameValue('SG', $reward['country'], 'country should be returned.');
assertSameValue('0', $reward['publisher_sub_id'], 'publisherSubId should be returned.');
assertSameValue(202604300647, $reward['timestamp'], 'timestamp should be returned.');

$devParams = $params;
unset($devParams['signature']);

$devService = new LevelPlayService([]);
$devReward = $devService->verifyRewardCallback($devParams, true);

assertSameValue('26abYb3da107f192380eY0', $devReward['event_id'], 'Dev mode should return event_id without signature.');
assertSameValue(10, $devReward['reward_amount'], 'Dev mode should return reward_amount without signature.');
