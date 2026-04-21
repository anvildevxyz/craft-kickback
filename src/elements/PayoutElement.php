<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements;

use anvildev\craftkickback\elements\db\PayoutQuery;
use anvildev\craftkickback\enums\PayoutMethod;
use anvildev\craftkickback\enums\PayoutStatus;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\PayoutRecord;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use DateTime;

class PayoutElement extends Element
{
    public const STATUS_PENDING = PayoutStatus::Pending->value;
    public const STATUS_PROCESSING = PayoutStatus::Processing->value;
    public const STATUS_COMPLETED = PayoutStatus::Completed->value;
    public const STATUS_FAILED = PayoutStatus::Failed->value;
    public const STATUS_REJECTED = PayoutStatus::Rejected->value;
    public const STATUS_REVERSED = PayoutStatus::Reversed->value;

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_REJECTED,
        self::STATUS_REVERSED,
    ];

    public const METHOD_PAYPAL = PayoutMethod::PayPal->value;
    public const METHOD_STRIPE = PayoutMethod::Stripe->value;
    public const METHOD_MANUAL = PayoutMethod::Manual->value;

    public const METHODS = [
        self::METHOD_PAYPAL,
        self::METHOD_STRIPE,
        self::METHOD_MANUAL,
    ];

    public ?int $affiliateId = null;
    public ?int $createdByUserId = null;
    public float $amount = 0.0;
    public string $currency = 'USD';
    public string $method = '';
    public string $payoutStatus = self::STATUS_PENDING;
    public ?string $transactionId = null;
    public ?string $gatewayBatchId = null;
    public ?string $notes = null;
    public ?DateTime $processedAt = null;

    public static function displayName(): string
    {
        return Craft::t('kickback', 'Payout');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('kickback', 'nav.payouts');
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
            self::STATUS_PROCESSING => [
                'label' => Craft::t('kickback', 'payoutStatus.processing'),
                'color' => 'blue',
            ],
            self::STATUS_COMPLETED => [
                'label' => Craft::t('kickback', 'payoutStatus.completed'),
                'color' => 'green',
            ],
            self::STATUS_FAILED => [
                'label' => Craft::t('kickback', 'payoutStatus.failed'),
                'color' => 'red',
            ],
            self::STATUS_REJECTED => [
                'label' => Craft::t('kickback', 'affiliateStatus.rejected'),
                'color' => 'red-orange',
            ],
            self::STATUS_REVERSED => [
                'label' => Craft::t('kickback', 'commissionStatus.reversed'),
                'color' => 'purple',
            ],
        ];
    }

    public function __toString(): string
    {
        return 'Payout #' . $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->payoutStatus;
    }

    public function getGqlTypeName(): string
    {
        return \anvildev\craftkickback\gql\types\generators\PayoutTypeGenerator::getName();
    }

    public static function find(): PayoutQuery
    {
        return new PayoutQuery(static::class);
    }

    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("kickback/payouts/{$this->id}");
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('kickback/payouts');
    }

    public function canView(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_PAYOUTS);
    }

    public function canSave(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_PAYOUTS);
    }

    public function canDelete(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_PAYOUTS);
    }

    protected static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('kickback', 'All Payouts'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'heading' => Craft::t('kickback', 'common.status'),
            ],
            [
                'key' => 'status:pending',
                'label' => Craft::t('kickback', 'affiliateStatus.pending'),
                'criteria' => ['payoutStatus' => self::STATUS_PENDING],
            ],
            [
                'key' => 'status:processing',
                'label' => Craft::t('kickback', 'payoutStatus.processing'),
                'criteria' => ['payoutStatus' => self::STATUS_PROCESSING],
            ],
            [
                'key' => 'status:completed',
                'label' => Craft::t('kickback', 'payoutStatus.completed'),
                'criteria' => ['payoutStatus' => self::STATUS_COMPLETED],
            ],
            [
                'key' => 'status:failed',
                'label' => Craft::t('kickback', 'payoutStatus.failed'),
                'criteria' => ['payoutStatus' => self::STATUS_FAILED],
            ],
            [
                'key' => 'status:rejected',
                'label' => Craft::t('kickback', 'affiliateStatus.rejected'),
                'criteria' => ['payoutStatus' => self::STATUS_REJECTED],
            ],
            [
                'key' => 'status:reversed',
                'label' => Craft::t('kickback', 'commissionStatus.reversed'),
                'criteria' => ['payoutStatus' => self::STATUS_REVERSED],
            ],
            [
                'heading' => Craft::t('kickback', 'Verification'),
            ],
            [
                'key' => 'verification:pending',
                'label' => Craft::t('kickback', 'Needs Verification'),
                'criteria' => ['verificationStatus' => 'pending'],
            ],
            [
                'key' => 'verification:rejected',
                'label' => Craft::t('kickback', 'Verification Rejected'),
                'criteria' => ['verificationStatus' => 'rejected'],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    protected static function defineActions(?string $source = null): array
    {
        if ($source === 'status:pending' || $source === 'status:processing') {
            return [
                \anvildev\craftkickback\elements\actions\CompletePayoutAction::class,
                \anvildev\craftkickback\elements\actions\FailPayoutAction::class,
            ];
        }

        return [];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'affiliateId' => ['label' => Craft::t('kickback', 'affiliate.one')],
            'amount' => ['label' => Craft::t('kickback', 'common.amount')],
            'method' => ['label' => Craft::t('kickback', 'Method')],
            'payoutStatus' => ['label' => Craft::t('kickback', 'common.status')],
            'verificationStatus' => ['label' => Craft::t('kickback', 'Verification')],
            'transactionId' => ['label' => Craft::t('kickback', 'common.transactionId')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'amount',
            'method',
            'payoutStatus',
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
                'orderBy' => 'kickback_payouts.amount',
                'attribute' => 'amount',
                'defaultDir' => 'desc',
            ],
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['transactionId', 'notes'];
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'amount' => Craft::$app->getFormatter()->asCurrency($this->amount, $this->currency),
            'method' => ucfirst(str_replace('_', ' ', $this->method)),
            'payoutStatus' => '<span class="status ' . (static::statuses()[$this->payoutStatus]['color'] ?? '') . '"></span>' . ucfirst($this->payoutStatus),
            'verificationStatus' => $this->renderVerificationBadge(),
            default => parent::attributeHtml($attribute),
        };
    }

    private function renderVerificationBadge(): string
    {
        $approval = KickBack::getInstance()
            ->approvals
            ->getFor('payout', (int)$this->id);

        if ($approval === null) {
            return '-';
        }

        return match ($approval->status) {
            'pending' => '<span class="status orange"></span>' . Craft::t('kickback', 'Awaiting verification'),
            'approved' => '<span class="status green"></span>' . Craft::t('kickback', 'Verified'),
            'rejected' => '<span class="status red"></span>' . Craft::t('kickback', 'affiliateStatus.rejected'),
            default => '-',
        };
    }

    protected function metaFieldsHtml(bool $static): string
    {
        $statusOptions = array_map(
            static fn(string $value, array $meta): array => ['label' => $meta['label'], 'value' => $value],
            array_keys(static::statuses()),
            static::statuses(),
        );

        $methodOptions = array_map(
            static fn(string $method): array => ['label' => ucfirst(str_replace('_', ' ', $method)), 'value' => $method],
            self::METHODS,
        );

        $fields = [
            Cp::selectFieldHtml([
                'label' => Craft::t('kickback', 'common.status'),
                'id' => 'payoutStatus', 'name' => 'payoutStatus',
                'value' => $this->payoutStatus, 'options' => $statusOptions, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'common.amount'),
                'id' => 'amount', 'name' => 'amount',
                'type' => 'number', 'value' => $this->amount, 'min' => 0, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'common.currency'),
                'id' => 'currency', 'name' => 'currency',
                'value' => $this->currency, 'maxlength' => 3, 'disabled' => $static,
            ]),
            Cp::selectFieldHtml([
                'label' => Craft::t('kickback', 'Method'),
                'id' => 'method', 'name' => 'method',
                'value' => $this->method, 'options' => $methodOptions, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'common.transactionId'),
                'id' => 'transactionId', 'name' => 'transactionId',
                'value' => $this->transactionId, 'disabled' => $static,
            ]),
        ];

        if ($this->processedAt !== null) {
            $fields[] = Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'Processed At'),
                'id' => 'processedAt', 'name' => 'processedAt',
                'value' => Craft::$app->getFormatter()->asDatetime($this->processedAt), 'disabled' => true,
            ]);
        }

        if ($this->notes !== null) {
            $fields[] = Cp::textareaFieldHtml([
                'label' => Craft::t('kickback', 'common.notes'),
                'id' => 'notes', 'name' => 'notes',
                'value' => $this->notes, 'rows' => 4, 'disabled' => $static,
            ]);
        }

        return implode("\n", $fields);
    }

    public function afterSave(bool $isNew): void
    {
        $record = $isNew ? new PayoutRecord() : (PayoutRecord::findOne($this->id)
            ?? throw new \RuntimeException("Invalid payout ID: {$this->id}"));
        if ($isNew) {
            $record->id = $this->id;
        }

        foreach (['affiliateId', 'createdByUserId', 'amount', 'currency', 'method',
            'transactionId', 'gatewayBatchId', 'notes', ] as $attr) {
            $record->$attr = $this->$attr;
        }
        $record->status = $this->payoutStatus;
        $record->processedAt = $this->processedAt?->format('Y-m-d H:i:s');
        $record->save(false);

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        $record = PayoutRecord::findOne($this->id);
        $record?->delete();

        // Polymorphic approvals table has no FK on targetId - cascade manually.
        KickBack::getInstance()->approvals->deleteFor('payout', (int)$this->id);

        parent::afterDelete();
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['amount', 'method', 'payoutStatus'], 'required'];
        $rules[] = [['amount'], 'number', 'min' => 0];
        $rules[] = [['currency'], 'string', 'length' => 3];
        $rules[] = [['method'], 'in', 'range' => self::METHODS];
        $rules[] = [['payoutStatus'], 'in', 'range' => self::STATUSES];
        $rules[] = [['transactionId'], 'string', 'max' => 255];

        return $rules;
    }
}
