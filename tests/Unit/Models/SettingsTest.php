<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Models;

use anvildev\craftkickback\models\Settings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        $this->settings = new Settings();
    }

    // --- Commission defaults ---

    #[Test]
    public function defaultCommissionTypeIsPercentage(): void
    {
        $this->assertSame('percentage', $this->settings->defaultCommissionType);
    }

    #[Test]
    public function defaultCommissionRateIsTen(): void
    {
        $this->assertSame(10.0, $this->settings->defaultCommissionRate);
    }

    // --- Cookie defaults ---

    #[Test]
    public function defaultCookieDurationIsThirty(): void
    {
        $this->assertSame(30, $this->settings->cookieDuration);
    }

    #[Test]
    public function defaultCookieName(): void
    {
        $this->assertSame('_kb_ref', $this->settings->cookieName);
    }

    // --- Attribution & tracking ---

    #[Test]
    public function defaultAttributionModelIsLastClick(): void
    {
        $this->assertSame('last_click', $this->settings->attributionModel);
    }

    #[Test]
    public function defaultReferralParamName(): void
    {
        $this->assertSame('ref', $this->settings->referralParamName);
    }

    #[Test]
    public function defaultClickRetentionDaysIs90(): void
    {
        $this->assertSame(90, $this->settings->clickRetentionDays);
    }

    #[Test]
    public function couponTrackingEnabledByDefault(): void
    {
        $this->assertTrue($this->settings->enableCouponTracking);
    }

    // --- Feature flags ---

    #[Test]
    public function lifetimeCommissionsDisabledByDefault(): void
    {
        $this->assertFalse($this->settings->enableLifetimeCommissions);
    }

    #[Test]
    public function multiTierDisabledByDefault(): void
    {
        $this->assertFalse($this->settings->enableMultiTier);
    }

    // --- Approval & payout ---

    #[Test]
    public function autoApproveAffiliatesDisabledByDefault(): void
    {
        $this->assertFalse($this->settings->autoApproveAffiliates);
    }

    #[Test]
    public function autoApproveReferralsDisabledByDefault(): void
    {
        $this->assertFalse($this->settings->autoApproveReferrals);
    }

    #[Test]
    public function defaultHoldPeriodIsThirtyDays(): void
    {
        $this->assertSame(30, $this->settings->holdPeriodDays);
    }

    #[Test]
    public function defaultMinimumPayoutAmount(): void
    {
        $this->assertSame(50.00, $this->settings->minimumPayoutAmount);
    }

    // --- Fraud detection ---

    #[Test]
    public function fraudDetectionEnabledByDefault(): void
    {
        $this->assertTrue($this->settings->enableFraudDetection);
    }

    #[Test]
    public function defaultFraudClickVelocityThreshold(): void
    {
        $this->assertSame(10, $this->settings->fraudClickVelocityThreshold);
    }

    #[Test]
    public function defaultFraudClickVelocityWindow(): void
    {
        $this->assertSame(60, $this->settings->fraudClickVelocityWindow);
    }

    #[Test]
    public function defaultFraudRapidConversionMinutes(): void
    {
        $this->assertSame(5, $this->settings->fraudRapidConversionMinutes);
    }

    #[Test]
    public function fraudAutoFlagEnabledByDefault(): void
    {
        $this->assertTrue($this->settings->fraudAutoFlag);
    }

    // --- MLM ---

    #[Test]
    public function defaultMaxMlmDepth(): void
    {
        $this->assertSame(3, $this->settings->maxMlmDepth);
    }

    // --- Order adjustments ---

    #[Test]
    public function excludeShippingByDefault(): void
    {
        $this->assertTrue($this->settings->excludeShippingFromCommission);
    }

    #[Test]
    public function excludeTaxByDefault(): void
    {
        $this->assertTrue($this->settings->excludeTaxFromCommission);
    }

    #[Test]
    public function reverseOnRefundByDefault(): void
    {
        $this->assertTrue($this->settings->reverseCommissionOnRefund);
    }

    // --- Gateway defaults ---

    #[Test]
    public function paypalSandboxEnabledByDefault(): void
    {
        $this->assertTrue($this->settings->paypalSandbox);
    }

    #[Test]
    public function gatewayCredentialsDefaultToEmpty(): void
    {
        $this->assertSame('', $this->settings->paypalClientId);
        $this->assertSame('', $this->settings->paypalClientSecret);
        $this->assertSame('', $this->settings->stripeSecretKey);
    }

    #[Test]
    public function batchAutoProcessDisabledByDefault(): void
    {
        $this->assertFalse($this->settings->batchAutoProcessEnabled);
    }

    // --- Misc ---

    #[Test]
    public function defaultAffiliatePortalPathsIsEmpty(): void
    {
        $this->assertSame([], $this->settings->affiliatePortalPaths);
    }

    #[Test]
    public function defaultCancelledStatusHandles(): void
    {
        $this->assertSame(['cancelled'], $this->settings->cancelledStatusHandles);
    }

    // --- Validation pattern tests (regex isolation - no Yii required) ---

    private const PORTAL_PATH_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9\/_-]*$/';
    private const PARAM_NAME_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    #[Test]
    public function validAffiliatePortalPathAccepted(): void
    {
        $this->assertSame(1, preg_match(self::PORTAL_PATH_PATTERN, 'affiliate'));
    }

    #[Test]
    public function affiliatePortalPathWithSlashesAccepted(): void
    {
        $this->assertSame(1, preg_match(self::PORTAL_PATH_PATTERN, 'my/portal/path'));
    }

    #[Test]
    public function affiliatePortalPathWithTraversalRejected(): void
    {
        $this->assertSame(0, preg_match(self::PORTAL_PATH_PATTERN, '../../admin'));
    }

    #[Test]
    public function affiliatePortalPathWithSpecialCharsRejected(): void
    {
        $this->assertSame(0, preg_match(self::PORTAL_PATH_PATTERN, 'path<script>'));
    }

    #[Test]
    public function validReferralParamNameAccepted(): void
    {
        $this->assertSame(1, preg_match(self::PARAM_NAME_PATTERN, 'ref'));
    }

    #[Test]
    public function referralParamNameWithUnderscoreStartAccepted(): void
    {
        $this->assertSame(1, preg_match(self::PARAM_NAME_PATTERN, '_ref_code'));
    }

    #[Test]
    public function referralParamNameStartingWithNumberRejected(): void
    {
        $this->assertSame(0, preg_match(self::PARAM_NAME_PATTERN, '1ref'));
    }

    #[Test]
    public function referralParamNameWithInjectionRejected(): void
    {
        $this->assertSame(0, preg_match(self::PARAM_NAME_PATTERN, "'; DROP TABLE"));
    }
}
