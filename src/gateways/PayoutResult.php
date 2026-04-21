<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gateways;

use anvildev\craftkickback\elements\PayoutElement;

class PayoutResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $transactionId = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $gatewayStatus = null,
        public readonly ?string $batchId = null,
    ) {
    }

    public static function succeeded(string $transactionId, ?string $batchId = null): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            gatewayStatus: PayoutElement::STATUS_COMPLETED,
            batchId: $batchId,
        );
    }

    public static function failed(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            gatewayStatus: PayoutElement::STATUS_FAILED,
        );
    }

    public static function pending(string $batchId): self
    {
        return new self(
            success: true,
            transactionId: $batchId,
            gatewayStatus: PayoutElement::STATUS_PENDING,
            batchId: $batchId,
        );
    }
}
