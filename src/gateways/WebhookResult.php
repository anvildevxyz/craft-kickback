<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gateways;

final class WebhookResult
{
    public function __construct(
        public readonly bool $verified,
        public readonly bool $processed,
        public readonly ?string $payoutId = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    public static function verified(bool $processed, ?string $payoutId = null): self
    {
        return new self(true, $processed, $payoutId);
    }

    public static function unverified(string $errorMessage): self
    {
        return new self(false, false, null, $errorMessage);
    }
}
