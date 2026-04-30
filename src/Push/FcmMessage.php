<?php

namespace Lemoba\MobileMonetization\Push;

class FcmMessage
{
    public function __construct(
        public readonly string $platform,
        public readonly array $response,
    ) {
    }

    public function name(): ?string
    {
        return $this->response['name'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'name' => $this->name(),
            'raw' => $this->response,
        ];
    }
}
