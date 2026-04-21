<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\elements\ReferralElement;
use anvildev\craftkickback\services\ReferralService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Covers the field-assignment behavior of
 * {@see ReferralService::applySubIdFromClick()}: given a referral element
 * and primitive click data, it must copy clickId and subId onto the element.
 * Referral creation (createReferral) is exercised by the integration suite.
 */
final class ReferralServiceSubIdTest extends TestCase
{
    #[Test]
    public function applySubIdFromClickAssignsElementFields(): void
    {
        $referral = self::makeReferralElement();

        ReferralService::applySubIdFromClick($referral, 42, 'campaign-fb-spring');

        self::assertSame('campaign-fb-spring', $referral->subId);
        self::assertSame(42, $referral->clickId);
    }

    #[Test]
    public function applySubIdFromClickLeavesSubIdNullWhenClickHasNone(): void
    {
        $referral = self::makeReferralElement();

        ReferralService::applySubIdFromClick($referral, 7, null);

        self::assertNull($referral->subId);
        self::assertSame(7, $referral->clickId);
    }

    /**
     * craft\base\Element::__construct() pulls in Craft services, so in pure
     * unit tests we skip the constructor and work directly with typed
     * public properties on the element.
     */
    private static function makeReferralElement(): ReferralElement
    {
        /** @var ReferralElement $element */
        $element = (new ReflectionClass(ReferralElement::class))->newInstanceWithoutConstructor();
        return $element;
    }
}
