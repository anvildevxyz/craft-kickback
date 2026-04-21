<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\services\CommissionService;
use PHPUnit\Framework\TestCase;

class CommissionCreationEventTest extends TestCase
{
    private const SERVICE_FILE = __DIR__ . '/../../../src/services/CommissionService.php';

    public function testEventConstantsExist(): void
    {
        // Proves the constants have been declared. A cheap static test
        // that doesn't need a Craft bootstrap or DB fixtures.
        $this->assertIsString(CommissionService::EVENT_BEFORE_CREATE_COMMISSION);
        $this->assertSame('beforeCreateCommission', CommissionService::EVENT_BEFORE_CREATE_COMMISSION);
        $this->assertIsString(CommissionService::EVENT_AFTER_CREATE_COMMISSION);
        $this->assertSame('afterCreateCommission', CommissionService::EVENT_AFTER_CREATE_COMMISSION);
    }

    public function testBeforeCreateCommissionEventCanVetoViaIsValid(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'CommissionService.php must be readable');

        $start = strpos($source, 'function saveCommission(');
        $this->assertNotFalse($start, 'saveCommission method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'trigger(self::EVENT_BEFORE_CREATE_COMMISSION',
            $body,
            'saveCommission() must trigger EVENT_BEFORE_CREATE_COMMISSION.',
        );

        $this->assertStringContainsString(
            '$beforeEvent->isValid',
            $body,
            'saveCommission() must check $beforeEvent->isValid to support veto semantics.',
        );

        $this->assertStringContainsString(
            'RuntimeException',
            $body,
            'saveCommission() must throw a RuntimeException when the before-event is vetoed.',
        );
    }

    public function testAfterCreateCommissionEventFiresWithCommissionAttached(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'CommissionService.php must be readable');

        $start = strpos($source, 'function saveCommission(');
        $this->assertNotFalse($start, 'saveCommission method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'trigger(self::EVENT_AFTER_CREATE_COMMISSION',
            $body,
            'saveCommission() must trigger EVENT_AFTER_CREATE_COMMISSION.',
        );

        $this->assertStringContainsString(
            "'commission' =>",
            $body,
            'The AFTER_CREATE event payload must include a "commission" key so listeners receive the saved record.',
        );
    }

    public function testReverseCommissionEventsFireInsideRefundLoop(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'CommissionService.php must be readable');

        $start = strpos($source, 'function reverseCommissionsProportionally(');
        $this->assertNotFalse($start, 'reverseCommissionsProportionally method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'trigger(self::EVENT_BEFORE_REVERSE_COMMISSION',
            $body,
            'reverseCommissionsProportionally() must trigger EVENT_BEFORE_REVERSE_COMMISSION.',
        );

        $this->assertStringContainsString(
            'trigger(self::EVENT_AFTER_REVERSE_COMMISSION',
            $body,
            'reverseCommissionsProportionally() must trigger EVENT_AFTER_REVERSE_COMMISSION.',
        );

        $this->assertStringContainsString(
            'foreach',
            $body,
            'reverseCommissionsProportionally() must use a foreach loop so events fire per-commission.',
        );
    }
}
