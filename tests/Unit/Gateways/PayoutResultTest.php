<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gateways;

use anvildev\craftkickback\gateways\PayoutResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PayoutResultTest extends TestCase
{
    #[Test]
    public function succeededSetsSuccessTrue(): void
    {
        $result = PayoutResult::succeeded('txn_123');
        $this->assertTrue($result->success);
    }

    #[Test]
    public function succeededSetsTransactionId(): void
    {
        $result = PayoutResult::succeeded('txn_abc');
        $this->assertSame('txn_abc', $result->transactionId);
    }

    #[Test]
    public function succeededSetsGatewayStatusToCompleted(): void
    {
        $result = PayoutResult::succeeded('txn_123');
        $this->assertSame('completed', $result->gatewayStatus);
    }

    #[Test]
    public function succeededHasNullErrorMessage(): void
    {
        $result = PayoutResult::succeeded('txn_123');
        $this->assertNull($result->errorMessage);
    }

    #[Test]
    public function succeededWithBatchId(): void
    {
        $result = PayoutResult::succeeded('txn_123', 'batch_456');
        $this->assertSame('batch_456', $result->batchId);
    }

    #[Test]
    public function succeededWithoutBatchIdDefaultsToNull(): void
    {
        $result = PayoutResult::succeeded('txn_123');
        $this->assertNull($result->batchId);
    }

    #[Test]
    public function failedSetsSuccessFalse(): void
    {
        $result = PayoutResult::failed('Something broke');
        $this->assertFalse($result->success);
    }

    #[Test]
    public function failedSetsErrorMessage(): void
    {
        $result = PayoutResult::failed('Insufficient funds');
        $this->assertSame('Insufficient funds', $result->errorMessage);
    }

    #[Test]
    public function failedSetsGatewayStatusToFailed(): void
    {
        $result = PayoutResult::failed('error');
        $this->assertSame('failed', $result->gatewayStatus);
    }

    #[Test]
    public function failedHasNullTransactionId(): void
    {
        $result = PayoutResult::failed('error');
        $this->assertNull($result->transactionId);
    }

    #[Test]
    public function failedHasNullBatchId(): void
    {
        $result = PayoutResult::failed('error');
        $this->assertNull($result->batchId);
    }

    #[Test]
    public function pendingSetsSuccessTrue(): void
    {
        $result = PayoutResult::pending('batch_789');
        $this->assertTrue($result->success);
    }

    #[Test]
    public function pendingSetsGatewayStatusToPending(): void
    {
        $result = PayoutResult::pending('batch_789');
        $this->assertSame('pending', $result->gatewayStatus);
    }

    #[Test]
    public function pendingSetsBatchIdAndTransactionIdToBatchId(): void
    {
        $result = PayoutResult::pending('batch_789');
        $this->assertSame('batch_789', $result->batchId);
        $this->assertSame('batch_789', $result->transactionId);
    }
}
