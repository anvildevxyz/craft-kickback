<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements;

use anvildev\craftkickback\elements\db\ReferralQuery;
use anvildev\craftkickback\gql\types\generators\ReferralTypeGenerator;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use DateTime;

class ReferralElement extends Element
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';
    public const STATUS_FLAGGED = 'flagged';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PAID,
        self::STATUS_FLAGGED,
    ];

    public const ATTRIBUTION_COOKIE = 'cookie';
    public const ATTRIBUTION_COUPON = 'coupon';
    public const ATTRIBUTION_DIRECT_LINK = 'direct_link';
    public const ATTRIBUTION_LIFETIME_CUSTOMER = 'lifetime_customer';
    public const ATTRIBUTION_MANUAL = 'manual';

    public const ATTRIBUTION_METHODS = [
        self::ATTRIBUTION_COOKIE,
        self::ATTRIBUTION_COUPON,
        self::ATTRIBUTION_DIRECT_LINK,
        self::ATTRIBUTION_LIFETIME_CUSTOMER,
        self::ATTRIBUTION_MANUAL,
    ];

    public ?int $affiliateId = null;
    public ?int $programId = null;
    public ?int $orderId = null;
    public ?int $clickId = null;
    public ?string $customerEmail = null;
    public ?int $customerId = null;
    public float $orderSubtotal = 0.0;
    public string $referralStatus = self::STATUS_PENDING;
    public string $attributionMethod = self::ATTRIBUTION_COOKIE;
    public ?string $couponCode = null;
    public ?string $referralResolutionTrace = null;
    public ?string $subId = null;
    public ?string $fraudFlags = null;
    public ?DateTime $dateApproved = null;
    public ?DateTime $datePaid = null;

    public function getGqlTypeName(): string
    {
        return ReferralTypeGenerator::getName();
    }

    public static function displayName(): string
    {
        return Craft::t('kickback', 'Referral');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('kickback', 'nav.referrals');
    }

    public static function hasContent(): bool
    {
        return false;
    }

    public static function hasTitles(): bool
    {
        return false;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => [
                'label' => Craft::t('kickback', 'affiliateStatus.pending'),
                'color' => 'orange',
            ],
            self::STATUS_APPROVED => [
                'label' => Craft::t('kickback', 'referralStatus.approved'),
                'color' => 'green',
            ],
            self::STATUS_REJECTED => [
                'label' => Craft::t('kickback', 'affiliateStatus.rejected'),
                'color' => 'red',
            ],
            self::STATUS_PAID => [
                'label' => Craft::t('kickback', 'Paid'),
                'color' => 'blue',
            ],
            self::STATUS_FLAGGED => [
                'label' => Craft::t('kickback', 'referralStatus.flagged'),
                'color' => 'orange',
            ],
        ];
    }

    public function __toString(): string
    {
        return 'Referral #' . $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->referralStatus;
    }

    public static function find(): ReferralQuery
    {
        return new ReferralQuery(static::class);
    }

    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("kickback/referrals/{$this->id}");
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('kickback/referrals');
    }

    public function canView(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_REFERRALS);
    }

    public function canSave(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_REFERRALS);
    }

    public function canDelete(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_REFERRALS);
    }

    protected static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('kickback', 'All Referrals'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'heading' => Craft::t('kickback', 'common.status'),
            ],
            [
                'key' => 'status:pending',
                'label' => Craft::t('kickback', 'affiliateStatus.pending'),
                'criteria' => ['referralStatus' => self::STATUS_PENDING],
            ],
            [
                'key' => 'status:approved',
                'label' => Craft::t('kickback', 'referralStatus.approved'),
                'criteria' => ['referralStatus' => self::STATUS_APPROVED],
            ],
            [
                'key' => 'status:flagged',
                'label' => Craft::t('kickback', 'referralStatus.flagged'),
                'criteria' => ['referralStatus' => self::STATUS_FLAGGED],
            ],
            [
                'key' => 'status:rejected',
                'label' => Craft::t('kickback', 'affiliateStatus.rejected'),
                'criteria' => ['referralStatus' => self::STATUS_REJECTED],
            ],
            [
                'key' => 'status:paid',
                'label' => Craft::t('kickback', 'Paid'),
                'criteria' => ['referralStatus' => self::STATUS_PAID],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    protected static function defineActions(?string $source = null): array
    {
        return match ($source) {
            'status:flagged' => [
                \anvildev\craftkickback\elements\actions\ApproveFraudAction::class,
                \anvildev\craftkickback\elements\actions\RejectFraudAction::class,
            ],
            'status:pending' => [
                \anvildev\craftkickback\elements\actions\ApproveReferralAction::class,
                \anvildev\craftkickback\elements\actions\RejectReferralAction::class,
            ],
            default => [],
        };
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'affiliateId' => ['label' => Craft::t('kickback', 'affiliate.one')],
            'orderSubtotal' => ['label' => Craft::t('kickback', 'referral.field.orderSubtotal')],
            'attributionMethod' => ['label' => Craft::t('kickback', 'Attribution Method')],
            'referralStatus' => ['label' => Craft::t('kickback', 'common.status')],
            'customerEmail' => ['label' => Craft::t('kickback', 'common.customerEmail')],
            'fraudFlags' => ['label' => Craft::t('kickback', 'fraud.flags')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'orderSubtotal',
            'attributionMethod',
            'referralStatus',
            'customerEmail',
            'dateCreated',
        ];
    }

    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('kickback', 'referral.field.orderSubtotal'),
                'orderBy' => 'kickback_referrals.orderSubtotal',
                'attribute' => 'orderSubtotal',
                'defaultDir' => 'desc',
            ],
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['customerEmail', 'couponCode'];
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'orderSubtotal' => Craft::$app->getFormatter()->asCurrency($this->orderSubtotal, KickBack::getCommerceCurrency()),
            'referralStatus' => '<span class="status ' . $this->getStatusColor() . '"></span>' . ucfirst($this->referralStatus),
            'attributionMethod' => ucfirst(str_replace('_', ' ', $this->attributionMethod)),
            'fraudFlags' => $this->renderFraudFlagsHtml(),
            default => parent::attributeHtml($attribute),
        };
    }

    /**
     * @return string[]
     */
    public function getFraudFlagsList(): array
    {
        if ($this->fraudFlags === null) {
            return [];
        }

        $decoded = Json::decodeIfJson($this->fraudFlags);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    private function renderFraudFlagsHtml(): string
    {
        $flags = $this->getFraudFlagsList();
        if ($flags === []) {
            return '';
        }

        return implode(' ', array_map(
            static fn(string $flag): string => '<span class="status-label">'
                . htmlspecialchars(explode(':', $flag)[0], ENT_QUOTES, 'UTF-8')
                . '</span>',
            $flags,
        ));
    }

    protected function metaFieldsHtml(bool $static): string
    {
        return Cp::textareaFieldHtml([
            'label' => Craft::t('kickback', 'Resolution Trace'),
            'value' => $this->formatTraceForDisplay($this->referralResolutionTrace),
            'disabled' => true,
            'rows' => 8,
        ]);
    }

    public function getStatusColor(): string
    {
        return static::statuses()[$this->referralStatus]['color'] ?? '';
    }

    public function getFormattedResolutionTrace(): string
    {
        return $this->formatTraceForDisplay($this->referralResolutionTrace);
    }

    private function formatTraceForDisplay(?string $trace): string
    {
        if ($trace === null || trim($trace) === '') {
            return Craft::t('app', '-');
        }

        $decoded = Json::decodeIfJson($trace);
        return is_array($decoded)
            ? Json::encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $trace;
    }

    public function afterSave(bool $isNew): void
    {
        $record = $isNew ? new ReferralRecord() : (ReferralRecord::findOne($this->id)
            ?? throw new \RuntimeException("Invalid referral ID: {$this->id}"));
        if ($isNew) {
            $record->id = $this->id;
        }

        foreach (['affiliateId', 'programId', 'orderId', 'clickId', 'customerEmail', 'customerId',
            'orderSubtotal', 'attributionMethod', 'couponCode', 'referralResolutionTrace', 'subId', 'fraudFlags', ] as $attr) {
            $record->$attr = $this->$attr;
        }
        $record->status = $this->referralStatus;
        $record->dateApproved = $this->dateApproved?->format('Y-m-d H:i:s');
        $record->datePaid = $this->datePaid?->format('Y-m-d H:i:s');
        $record->save(false);

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        $record = ReferralRecord::findOne($this->id);
        $record?->delete();

        parent::afterDelete();
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['programId'], 'required'];
        $rules[] = [['orderSubtotal'], 'number', 'min' => 0];
        $rules[] = [['referralStatus'], 'in', 'range' => self::STATUSES];
        $rules[] = [['attributionMethod'], 'in', 'range' => self::ATTRIBUTION_METHODS];
        $rules[] = [['couponCode'], 'string', 'max' => 64];
        $rules[] = [['subId'], 'string', 'max' => 255];

        return $rules;
    }
}
