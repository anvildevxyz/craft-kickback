<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Models;

use anvildev\craftkickback\models\CustomerLink;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CustomerLinkTest extends TestCase
{
    #[Test]
    public function defaultCustomerEmailIsEmptyString(): void
    {
        $link = new CustomerLink();
        $this->assertSame('', $link->customerEmail);
    }

    #[Test]
    public function defaultNullableFieldsAreNull(): void
    {
        $link = new CustomerLink();
        $this->assertNull($link->id);
        $this->assertNull($link->affiliateId);
        $this->assertNull($link->customerId);
    }

    #[Test]
    public function constructorAcceptsConfig(): void
    {
        $link = new CustomerLink([
            'customerEmail' => 'test@example.com',
            'affiliateId' => 42,
        ]);
        $this->assertSame('test@example.com', $link->customerEmail);
        $this->assertSame(42, $link->affiliateId);
    }
}
