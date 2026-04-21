<?php

declare(strict_types=1);

namespace anvildev\craftkickback\models;

use anvildev\craftkickback\enums\AttributionMethod;
use anvildev\craftkickback\enums\ReferralStatus;
use craft\base\Model;
use DateTime;

class Referral extends Model
{
    public const STATUS_PENDING = ReferralStatus::Pending->value;
    public const STATUS_APPROVED = ReferralStatus::Approved->value;
    public const STATUS_REJECTED = ReferralStatus::Rejected->value;
    public const STATUS_PAID = ReferralStatus::Paid->value;
    public const STATUS_FLAGGED = ReferralStatus::Flagged->value;

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PAID,
        self::STATUS_FLAGGED,
    ];

    public const ATTRIBUTION_COOKIE = AttributionMethod::Cookie->value;
    public const ATTRIBUTION_COUPON = AttributionMethod::Coupon->value;
    public const ATTRIBUTION_DIRECT_LINK = AttributionMethod::DirectLink->value;
    public const ATTRIBUTION_LIFETIME_CUSTOMER = AttributionMethod::LifetimeCustomer->value;
    public const ATTRIBUTION_MANUAL = AttributionMethod::Manual->value;

    public const ATTRIBUTION_METHODS = [
        self::ATTRIBUTION_COOKIE,
        self::ATTRIBUTION_COUPON,
        self::ATTRIBUTION_DIRECT_LINK,
        self::ATTRIBUTION_LIFETIME_CUSTOMER,
        self::ATTRIBUTION_MANUAL,
    ];

    public ?int $id = null;
    public ?int $affiliateId = null;
    public ?int $programId = null;
    public ?int $orderId = null;
    public ?int $clickId = null;
    public ?string $customerEmail = null;
    public ?int $customerId = null;
    public float $orderSubtotal = 0.0;
    public string $status = self::STATUS_PENDING;
    public string $attributionMethod = self::ATTRIBUTION_COOKIE;
    public ?string $couponCode = null;
    /** @var list<string>|null */
    public ?array $fraudFlags = null;
    public ?DateTime $dateApproved = null;
    public ?DateTime $datePaid = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['affiliateId', 'programId'], 'required'];
        $rules[] = [['affiliateId', 'programId', 'orderId', 'clickId', 'customerId'], 'integer'];
        $rules[] = [['customerEmail'], 'email'];
        $rules[] = [['orderSubtotal'], 'number', 'min' => 0];
        $rules[] = [['status'], 'in', 'range' => self::STATUSES];
        $rules[] = [['attributionMethod'], 'in', 'range' => self::ATTRIBUTION_METHODS];
        $rules[] = [['couponCode'], 'string', 'max' => 64];

        return $rules;
    }
}
