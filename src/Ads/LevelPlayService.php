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
        $secret = $this->config['secret'] ?? null;

        if (!$secret) {
            throw new MobileMonetizationException('LEVELPLAY_SECRET is required.');
        }

        $appUserId = (string) ($data['appUserId'] ?? '');
        $dynamicUserId = (string) ($data['dynamicUserId'] ?? '');
        $eventId = (string) ($data['eventId'] ?? '');
        $rewards = (string) ($data['rewards'] ?? '');
        $timestamp = (string) ($data['timestamp'] ?? '');
        $signature = strtolower((string) ($data['signature'] ?? ''));

        if ($appUserId === '' || $eventId === '' || $rewards === '' || $timestamp === '' || $signature === '') {
            throw new MobileMonetizationException('LevelPlay callback is missing required fields.', 422, $data);
        }

        $expected = md5($timestamp . $eventId . $appUserId . $rewards . (string) $secret);
        if (!hash_equals($expected, $signature)) {
            throw new MobileMonetizationException('Invalid LevelPlay callback signature.', 401, $data);
        }

        $customParameters = $this->parseCustomParameters($data);

        return [
            'event_id' => $eventId,
            'user_id' => $appUserId,
            'app_user_id' => $appUserId,
            'dynamic_user_id' => $dynamicUserId !== '' ? $dynamicUserId : null,
            'reward_item' => $data['rewardName'] ?? $data['itemName'] ?? $this->config['reward_item'] ?? 'coins',
            'reward_amount' => (int) $rewards,
            'rewards' => $rewards,
            'country' => $data['country'] ?? null,
            'publisher_sub_id' => $data['publisherSubId'] ?? null,
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
            'appUserId',
            'userId',
            'dynamicUserId',
            'applicationUserId',
            'userid',
            'eventId',
            'rewardName',
            'rewardAmount',
            'rewards',
            'country',
            'publisherSubId',
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
