<?php

declare(strict_types=1);

namespace anvildev\craftkickback\models;

use anvildev\craftkickback\enums\AttributionModel;
use Craft;
use craft\base\Model;
use craft\records\User as UserRecord;

/**
 * Global Kickback plugin settings.
 */
class Settings extends Model
{
    public const ATTRIBUTION_MODEL_FIRST_CLICK = AttributionModel::FirstClick->value;
    public const ATTRIBUTION_MODEL_LAST_CLICK = AttributionModel::LastClick->value;
    public const ATTRIBUTION_MODEL_LINEAR = AttributionModel::Linear->value;

    public const ATTRIBUTION_MODELS = [
        self::ATTRIBUTION_MODEL_FIRST_CLICK,
        self::ATTRIBUTION_MODEL_LAST_CLICK,
        self::ATTRIBUTION_MODEL_LINEAR,
    ];

    public string $defaultCommissionType = Commission::RATE_TYPE_PERCENTAGE;
    public float $defaultCommissionRate = 10.0;
    public int $cookieDuration = 30;
    public string $cookieName = '_kb_ref';
    public string $attributionModel = self::ATTRIBUTION_MODEL_LAST_CLICK;
    public string $referralParamName = 'ref';
    /** Days to keep click records. 0 = keep forever. */
    public int $clickRetentionDays = 90;
    public bool $enableCouponTracking = true;
    public bool $enableLifetimeCommissions = false;

    public bool $enableCouponCreation = true;
    public bool $allowAffiliateSelfServiceCoupons = false;
    public int $maxCouponsPerAffiliate = 5;
    public float $maxSelfServiceDiscountPercent = 50.0;

    public bool $autoApproveAffiliates = false;
    public bool $autoApproveReferrals = false;
    public int $holdPeriodDays = 30;
    public float $minimumPayoutAmount = 50.00;
    public bool $enableFraudDetection = true;
    public int $fraudClickVelocityThreshold = 10;
    public int $fraudClickVelocityWindow = 60;
    public int $fraudRapidConversionMinutes = 5;
    public int $fraudIpReuseThreshold = 5;
    public bool $fraudAutoFlag = true;
    public bool $enableMultiTier = false;
    public int $maxMlmDepth = 3;
    public bool $excludeShippingFromCommission = true;
    public bool $excludeTaxFromCommission = true;
    public bool $reverseCommissionOnRefund = true;
    /** @var array<string, string> Site handle → portal URL path. */
    public array $affiliatePortalPaths = [];

    /**
     * @var array<string, bool|string> Site handle → truthy when the portal is
     *     enabled on that site. Lightswitch-hydrated - isset() is the "is enabled" check.
     */
    public array $affiliatePortalEnabledSites = [];
    /** @var list<string> */
    public array $cancelledStatusHandles = ['cancelled'];

    public string $paypalClientId = '';
    public string $paypalClientSecret = '';
    public bool $paypalSandbox = true;
    public string $paypalWebhookId = '';
    public string $stripeSecretKey = '';
    public string $stripeWebhookSecret = '';

    public const CADENCE_WEEKLY = 'weekly';
    public const CADENCE_BIWEEKLY = 'biweekly';
    public const CADENCE_MONTHLY = 'monthly';
    public const CADENCE_QUARTERLY = 'quarterly';

    public const CADENCES = [
        self::CADENCE_WEEKLY,
        self::CADENCE_BIWEEKLY,
        self::CADENCE_MONTHLY,
        self::CADENCE_QUARTERLY,
    ];

    public bool $batchAutoProcessEnabled = false;
    public string $batchAutoProcessCadence = self::CADENCE_MONTHLY;
    public ?string $batchAutoProcessLastRun = null;

    public bool $requirePayoutVerification = false;
    public ?int $defaultPayoutVerifierId = null;
    public bool $notifyVerifierOnRequest = true;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['defaultCommissionType', 'cookieName', 'attributionModel', 'referralParamName',
            'defaultCommissionRate', 'cookieDuration', 'holdPeriodDays', 'minimumPayoutAmount', 'maxMlmDepth', ], 'required'];

        $rules[] = [['defaultCommissionType'], 'in', 'range' => Commission::RATE_TYPES];
        $rules[] = [['attributionModel'], 'in', 'range' => self::ATTRIBUTION_MODELS];
        $rules[] = [['batchAutoProcessCadence'], 'in', 'range' => self::CADENCES];

        $rules[] = [['defaultCommissionRate', 'minimumPayoutAmount'], 'number', 'min' => 0];
        $rules[] = [['maxSelfServiceDiscountPercent'], 'number', 'min' => 1, 'max' => 100];

        $rules[] = [['cookieDuration'], 'integer', 'min' => 1];
        $rules[] = [['holdPeriodDays', 'fraudRapidConversionMinutes'], 'integer', 'min' => 0];
        $rules[] = [['clickRetentionDays'], 'integer', 'min' => 0];
        $rules[] = [['fraudClickVelocityThreshold', 'fraudClickVelocityWindow', 'fraudIpReuseThreshold'], 'integer', 'min' => 1];
        $rules[] = [['maxMlmDepth'], 'integer', 'min' => 1, 'max' => 10];
        $rules[] = [['maxCouponsPerAffiliate'], 'integer', 'min' => 1, 'max' => 1000];
        $rules[] = [['defaultPayoutVerifierId'], 'integer'];

        $rules[] = [['cookieName', 'referralParamName'], 'string'];
        $rules[] = [['paypalClientId', 'paypalClientSecret', 'paypalWebhookId', 'stripeSecretKey', 'stripeWebhookSecret'], 'string'];
        $rules[] = [['referralParamName'], 'match', 'pattern' => '/^[a-zA-Z_][a-zA-Z0-9_]*$/'];

        $rules[] = [['enableCouponCreation', 'allowAffiliateSelfServiceCoupons', 'requirePayoutVerification', 'notifyVerifierOnRequest'], 'boolean'];

        $rules[] = [['affiliatePortalPaths'], 'each', 'rule' => ['match', 'pattern' => '/^[a-zA-Z0-9][a-zA-Z0-9\/_-]*$/']];
        $rules[] = [['cancelledStatusHandles'], 'each', 'rule' => ['string']];
        $rules[] = [['defaultPayoutVerifierId'], 'exist',
            'targetClass' => UserRecord::class,
            'targetAttribute' => 'id',
            'skipOnEmpty' => true,
        ];
        // maybeAnnounce() short-circuits on null requestedUserId, so force
        // a verifier when the feature is on - otherwise approvals land silently.
        $rules[] = [['defaultPayoutVerifierId'], 'required',
            'when' => fn(self $model) => (bool)$model->requirePayoutVerification,
            'message' => Craft::t('kickback', 'A default verifier is required when payout verification is enabled.'),
        ];
        // Permission string is inlined (not a KickBack::PERMISSION_* ref) to
        // avoid a circular import at boot time.
        $rules[] = [['defaultPayoutVerifierId'], function(string $attribute): void {
            if ($this->$attribute === null) {
                return;
            }
            $user = Craft::$app->getUsers()->getUserById((int)$this->$attribute);
            if ($user === null) {
                return;
            }
            if (!$user->can('kickback-verifyPayouts')) {
                $this->addError(
                    $attribute,
                    Craft::t('kickback', 'Selected user does not have permission to verify payouts.'),
                );
            }
        }];

        return $rules;
    }

    /**
     * Portal URL path for the current request's site, or null if disabled.
     */
    public function getCurrentSitePortalPath(): ?string
    {
        $handle = Craft::$app->getSites()->getCurrentSite()->handle;
        return isset($this->affiliatePortalEnabledSites[$handle])
            ? ($this->affiliatePortalPaths[$handle] ?: null)
            : null;
    }
}
