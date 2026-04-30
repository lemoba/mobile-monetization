<?php

namespace Lemoba\MobileMonetization\Payments;

class VerifiedPurchase
{
    public function __construct(
        public readonly string $platform,
        public readonly string $productId,
        public readonly string $transactionId,
        public readonly string $originalTransactionId,
        public readonly string $type,
        public readonly bool $valid,
        public readonly bool $consumable,
        public readonly ?int $purchasedAtMs = null,
        public readonly ?int $expiresAtMs = null,
        public readonly ?string $environment = null,
        public readonly array $raw = [],
    ) {
    }

    public function active(): bool
    {
        return $this->valid && ($this->expiresAtMs === null || $this->expiresAtMs > (int) floor(microtime(true) * 1000));
    }

    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'product_id' => $this->productId,
            'transaction_id' => $this->transactionId,
            'original_transaction_id' => $this->originalTransactionId,
            'type' => $this->type,
            'valid' => $this->valid,
            'active' => $this->active(),
            'consumable' => $this->consumable,
            'purchased_at_ms' => $this->purchasedAtMs,
            'expires_at_ms' => $this->expiresAtMs,
            'environment' => $this->environment,
            'raw' => $this->raw,
        ];
    }
}
