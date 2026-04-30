<?php

namespace Lemoba\MobileMonetization\Ads;

use Illuminate\Http\Request;
use Lemoba\MobileMonetization\Exceptions\MobileMonetizationException;

class LevelPlayService
{
    public function __construct(private readonly array $config)
    {
    }

    public function verifyRewardCallback(Request|array $input): array
    {
        $data = $input instanceof Request ? $input->query() + $input->post() : $input;
        $appKey = $this->config['key'] ?? null;
        $secret = $this->config['secret'] ?? null;

        if (!$appKey || !$secret) {
            throw new MobileMonetizationException('LEVELPLAY_KEY and LEVELPLAY_SECRET are required.');
        }

        $callbackAppKey = (string) ($data['appKey'] ?? '');
        $userId = (string) ($data['userId'] ?? $data['dynamicUserId'] ?? $data['applicationUserId'] ?? $data['userid'] ?? '');
        $transId = (string) ($data['transId'] ?? '');
        $rewardName = (string) ($data['rewardName'] ?? '');
        $rewardAmount = (string) ($data['rewardAmount'] ?? '');
        $timestamp = (string) ($data['timestamp'] ?? '');
        $signature = strtolower((string) ($data['signature'] ?? ''));

        if ($callbackAppKey === '' || $userId === '' || $transId === '' || $rewardName === '' || $rewardAmount === '' || $timestamp === '' || $signature === '') {
            throw new MobileMonetizationException('LevelPlay callback is missing required fields.', 422, $data);
        }

        if (!hash_equals((string) $appKey, $callbackAppKey)) {
            throw new MobileMonetizationException('Invalid LevelPlay app key.', 401, $data);
        }

        $payload = $callbackAppKey . $userId . $transId . $rewardName . $rewardAmount . $timestamp;
        $expected = hash_hmac('sha256', $payload, (string) $secret);
        if (!hash_equals($expected, $signature)) {
            throw new MobileMonetizationException('Invalid LevelPlay callback signature.', 401, $data);
        }

        $customParameters = $this->parseCustomParameters($data);

        return [
            'event_id' => $transId,
            'user_id' => $userId,
            'reward_item' => $rewardName,
            'reward_amount' => (int) $rewardAmount,
            'custom_parameters' => $customParameters,
            'order_id' => $customParameters['order_id'] ?? null,
            'ad_unit' => $data['adUnit'] ?? null,
            'placement' => $data['placement'] ?? $data['placementName'] ?? null,
            'network' => $data['network'] ?? $data['providerName'] ?? null,
            'timestamp' => (int) $timestamp,
            'raw' => $data,
        ];
    }

    public function okResponse(string $eventId): string
    {
        return $eventId . ':OK';
    }

    private function parseCustomParameters(array $data): array
    {
        if (!empty($data['customParameters']) && is_string($data['customParameters'])) {
            $custom = [];
            parse_str(rawurldecode($data['customParameters']), $custom);

            return $custom;
        }

        $reserved = [
            'appKey',
            'userId',
            'dynamicUserId',
            'applicationUserId',
            'userid',
            'transId',
            'rewardName',
            'rewardAmount',
            'timestamp',
            'signature',
            'adUnit',
            'placement',
            'placementName',
            'network',
            'providerName',
        ];

        return array_diff_key($data, array_flip($reserved));
    }
}
