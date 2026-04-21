<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements;

use anvildev\craftkickback\elements\db\CommissionQuery;
use anvildev\craftkickback\gql\types\generators\CommissionTypeGenerator;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\records\CommissionRecord;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use DateTime;

class CommissionElement extends Element
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_PAID,
        self::STATUS_REVERSED,
        self::STATUS_REJECTED,
    ];

    public ?int $referralId = null;
    public ?int $affiliateId = null;
    public float $amount = 0.0;
    public float $originalAmount = 0.0;
    public string $currency = 'USD';
    public float $rate = 0.0;
    public string $rateType = '';
    public ?string $ruleApplied = null;
    public ?string $ruleResolutionTrace = null;
    public int $tier = 1;
    public string $commissionStatus = self::STATUS_PENDING;
    public ?int $payoutId = null;
    public ?string $description = null;
    public ?DateTime $dateApproved = null;
    public ?DateTime $dateReversed = null;

    public function getGqlTypeName(): string
    {
        return CommissionTypeGenerator::getName();
    }

    public static function displayName(): string
    {
        return Craft::t('kickback', 'common.commission');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('kickback', 'nav.commissions');
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
            self::STATUS_PAID => [
                'label' => Craft::t('kickback', 'Paid'),
                'color' => 'blue',
            ],
            self::STATUS_REVERSED => [
                'label' => Craft::t('kickback', 'commissionStatus.reversed'),
                'color' => 'red',
            ],
            self::STATUS_REJECTED => [
                'label' => Craft::t('kickback', 'affiliateStatus.rejected'),
                'color' => 'red',
            ],
        ];
    }

    public function __toString(): string
    {
        return 'Commission #' . $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->commissionStatus;
    }

    public static function find(): CommissionQuery
    {
        return new CommissionQuery(static::class);
    }

    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("kickback/commissions/{$this->id}");
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('kickback/commissions');
    }

    public function canView(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_COMMISSIONS)
            || $user->can(KickBack::PERMISSION_APPROVE_COMMISSIONS);
    }

    public function canSave(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_COMMISSIONS);
    }

    public function canDelete(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_COMMISSIONS);
    }

    protected static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('kickback', 'All Commissions'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'heading' => Craft::t('kickback', 'common.status'),
            ],
            [
                'key' => 'status:pending',
                'label' => Craft::t('kickback', 'affiliateStatus.pending'),
                'criteria' => ['commissionStatus' => self::STATUS_PENDING],
            ],
            [
                'key' => 'status:approved',
                'label' => Craft::t('kickback', 'referralStatus.approved'),
                'criteria' => ['commissionStatus' => self::STATUS_APPROVED],
            ],
            [
                'key' => 'status:paid',
                'label' => Craft::t('kickback', 'Paid'),
                'criteria' => ['commissionStatus' => self::STATUS_PAID],
            ],
            [
                'key' => 'status:reversed',
                'label' => Craft::t('kickback', 'commissionStatus.reversed'),
                'criteria' => ['commissionStatus' => self::STATUS_REVERSED],
            ],
            [
                'key' => 'status:rejected',
                'label' => Craft::t('kickback', 'affiliateStatus.rejected'),
                'criteria' => ['commissionStatus' => self::STATUS_REJECTED],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'affiliateId' => ['label' => Craft::t('kickback', 'affiliate.one')],
            'amount' => ['label' => Craft::t('kickback', 'common.amount')],
            'rate' => ['label' => Craft::t('kickback', 'common.rate')],
            'ruleApplied' => ['label' => Craft::t('kickback', 'referral.field.ruleApplied')],
            'commissionStatus' => ['label' => Craft::t('kickback', 'common.status')],
            'tier' => ['label' => Craft::t('kickback', 'common.tier')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'amount',
            'ruleApplied',
            'commissionStatus',
            'tier',
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
                'label' => Craft::t('kickback', 'common.amount'),
                'orderBy' => 'kickback_commissions.amount',
                'attribute' => 'amount',
                'defaultDir' => 'desc',
            ],
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['ruleApplied', 'description'];
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'amount' => Craft::$app->getFormatter()->asCurrency($this->amount, $this->currency),
            'rate' => $this->rateType === Commission::RATE_TYPE_PERCENTAGE
                ? $this->rate . '%'
                : Craft::$app->getFormatter()->asCurrency($this->rate, $this->currency),
            'commissionStatus' => '<span class="status ' . $this->getStatusColor() . '"></span>' . ucfirst($this->commissionStatus),
            default => parent::attributeHtml($attribute),
        };
    }

    protected function metaFieldsHtml(bool $static): string
    {
        return Cp::textareaFieldHtml([
            'label' => Craft::t('kickback', 'Rule Resolution Trace'),
            'value' => $this->formatTraceForDisplay($this->ruleResolutionTrace),
            'disabled' => true,
            'rows' => 10,
        ]);
    }

    public function getStatusColor(): string
    {
        return static::statuses()[$this->commissionStatus]['color'] ?? '';
    }

    public function getFormattedResolutionTrace(): string
    {
        return $this->formatTraceForDisplay($this->ruleResolutionTrace);
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
        $record = $isNew ? new CommissionRecord() : (CommissionRecord::findOne($this->id)
            ?? throw new \RuntimeException("Invalid commission ID: {$this->id}"));
        if ($isNew) {
            $record->id = $this->id;
        }

        foreach (['referralId', 'affiliateId', 'amount', 'currency', 'rate', 'rateType', 'ruleApplied',
            'ruleResolutionTrace', 'tier', 'payoutId', 'description', ] as $attr) {
            $record->$attr = $this->$attr;
        }
        // originalAmount is immutable - only seed on initial save
        if ($isNew) {
            $record->originalAmount = $this->originalAmount > 0 ? $this->originalAmount : $this->amount;
        }
        $record->status = $this->commissionStatus;
        $record->dateApproved = $this->dateApproved?->format('Y-m-d H:i:s');
        $record->dateReversed = $this->dateReversed?->format('Y-m-d H:i:s');
        $record->save(false);

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        $record = CommissionRecord::findOne($this->id);
        $record?->delete();

        parent::afterDelete();
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['amount', 'rate', 'rateType'], 'required'];
        $rules[] = [['amount', 'rate'], 'number', 'min' => 0];
        $rules[] = [['currency'], 'string', 'length' => 3];
        $rules[] = [['rateType'], 'in', 'range' => Commission::RATE_TYPES];
        $rules[] = [['commissionStatus'], 'in', 'range' => self::STATUSES];
        $rules[] = [['tier'], 'integer', 'min' => 1];
        $rules[] = [['ruleApplied', 'description'], 'string', 'max' => 255];

        return $rules;
    }
}
