<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Models;

use anvildev\craftkickback\models\Referral;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReferralTest extends TestCase
{
    #[Test]
    public function statusConstantValues(): void
    {
        $this->assertSame('pending', Referral::STATUS_PENDING);
        $this->assertSame('approved', Referral::STATUS_APPROVED);
        $this->assertSame('rejected', Referral::STATUS_REJECTED);
        $this->assertSame('paid', Referral::STATUS_PAID);
        $this->assertSame('flagged', Referral::STATUS_FLAGGED);
    }

    #[Test]
    public function allStatusConstantsInStatusesArray(): void
    {
        $this->assertContains(Referral::STATUS_PENDING, Referral::STATUSES);
        $this->assertContains(Referral::STATUS_APPROVED, Referral::STATUSES);
        $this->assertContains(Referral::STATUS_REJECTED, Referral::STATUSES);
        $this->assertContains(Referral::STATUS_PAID, Referral::STATUSES);
        $this->assertContains(Referral::STATUS_FLAGGED, Referral::STATUSES);
    }

    #[Test]
    public function defaultStatusIsPending(): void
    {
        $referral = new Referral();
        $this->assertSame('pending', $referral->status);
    }

    #[Test]
    public function defaultOrderSubtotalIsZero(): void
    {
        $referral = new Referral();
        $this->assertSame(0.0, $referral->orderSubtotal);
    }

    #[Test]
    public function defaultAttributionMethodIsCookie(): void
    {
        $referral = new Referral();
        $this->assertSame('cookie', $referral->attributionMethod);
    }

    #[Test]
    public function defaultNullableFieldsAreNull(): void
    {
        $referral = new Referral();
        $this->assertNull($referral->id);
        $this->assertNull($referral->affiliateId);
        $this->assertNull($referral->orderId);
        $this->assertNull($referral->clickId);
        $this->assertNull($referral->customerEmail);
        $this->assertNull($referral->couponCode);
        $this->assertNull($referral->fraudFlags);
    }

    #[Test]
    public function statusesContainsFiveEntries(): void
    {
        $this->assertCount(5, Referral::STATUSES);
    }

    #[Test]
    public function constructorAcceptsConfig(): void
    {
        $referral = new Referral([
            'status' => 'approved',
            'orderSubtotal' => 99.99,
        ]);
        $this->assertSame('approved', $referral->status);
        $this->assertSame(99.99, $referral->orderSubtotal);
    }
}
