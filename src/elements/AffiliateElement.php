<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements;

use anvildev\craftkickback\elements\db\AffiliateQuery;
use anvildev\craftkickback\enums\AffiliateStatus;
use anvildev\craftkickback\gql\types\generators\AffiliateTypeGenerator;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\records\AffiliateRecord;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use DateTime;

class AffiliateElement extends Element
{
    public const STATUS_ACTIVE = AffiliateStatus::Active->value;
    public const STATUS_PENDING = AffiliateStatus::Pending->value;
    public const STATUS_SUSPENDED = AffiliateStatus::Suspended->value;
    public const STATUS_REJECTED = AffiliateStatus::Rejected->value;

    public ?int $userId = null;
    public ?int $programId = null;
    public string $affiliateStatus = self::STATUS_PENDING;
    public string $referralCode = '';
    public ?float $commissionRateOverride = null;
    public ?string $commissionTypeOverride = null;
    public ?int $parentAffiliateId = null;
    public int $tierLevel = 1;
    public ?int $groupId = null;
    public ?string $paypalEmail = null;
    public ?string $stripeAccountId = null;
    public string $payoutMethod = PayoutElement::METHOD_MANUAL;
    public float $payoutThreshold = 50.0;
    public float $lifetimeEarnings = 0.0;
    public int $lifetimeReferrals = 0;
    public float $pendingBalance = 0.0;
    public ?string $notes = null;
    public ?DateTime $dateApproved = null;

    private ?User $_user = null;

    public static function displayName(): string
    {
        return Craft::t('kickback', 'affiliate.one');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('kickback', 'nav.affiliates');
    }

    public static function hasContent(): bool
    {
        return false;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => [
                'label' => Craft::t('kickback', 'affiliateStatus.active'),
                'color' => 'green',
            ],
            self::STATUS_PENDING => [
                'label' => Craft::t('kickback', 'affiliateStatus.pending'),
                'color' => 'orange',
            ],
            self::STATUS_SUSPENDED => [
                'label' => Craft::t('kickback', 'affiliateStatus.suspended'),
                'color' => 'red',
            ],
            self::STATUS_REJECTED => [
                'label' => Craft::t('kickback', 'affiliateStatus.rejected'),
                'color' => 'red',
            ],
        ];
    }

    public function getStatus(): ?string
    {
        return $this->affiliateStatus;
    }

    public static function find(): AffiliateQuery
    {
        return new AffiliateQuery(static::class);
    }

    public function getUser(): ?User
    {
        return $this->_user ??= $this->userId !== null
            ? User::find()->id($this->userId)->one()
            : null;
    }

    public function setUser(User $user): void
    {
        $this->_user = $user;
        $this->userId = $user->id;
    }

    public function getReferralUrl(?string $url = null): string
    {
        $baseUrl = $url ?? Craft::$app->getSites()->getCurrentSite()->getBaseUrl();

        return rtrim($baseUrl, '/') . '/r/' . $this->referralCode;
    }

    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("kickback/affiliates/{$this->id}");
    }

    public function getStatusColor(): string
    {
        return static::statuses()[$this->affiliateStatus]['color'] ?? '';
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('kickback/affiliates');
    }

    public function canView(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_AFFILIATES);
    }

    public function canSave(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_AFFILIATES);
    }

    public function canDelete(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_AFFILIATES);
    }

    protected static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('kickback', 'affiliate.all'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'heading' => Craft::t('kickback', 'common.status'),
            ],
            [
                'key' => 'status:active',
                'label' => Craft::t('kickback', 'affiliateStatus.active'),
                'criteria' => ['affiliateStatus' => self::STATUS_ACTIVE],
            ],
            [
                'key' => 'status:pending',
                'label' => Craft::t('kickback', 'affiliateStatus.pending'),
                'criteria' => ['affiliateStatus' => self::STATUS_PENDING],
            ],
            [
                'key' => 'status:suspended',
                'label' => Craft::t('kickback', 'affiliateStatus.suspended'),
                'criteria' => ['affiliateStatus' => self::STATUS_SUSPENDED],
            ],
            [
                'key' => 'status:rejected',
                'label' => Craft::t('kickback', 'affiliateStatus.rejected'),
                'criteria' => ['affiliateStatus' => self::STATUS_REJECTED],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'referralCode' => ['label' => Craft::t('kickback', 'affiliate.field.referralCode')],
            'affiliateStatus' => ['label' => Craft::t('kickback', 'common.status')],
            'lifetimeEarnings' => ['label' => Craft::t('kickback', 'affiliate.field.lifetimeEarnings')],
            'lifetimeReferrals' => ['label' => Craft::t('kickback', 'nav.referrals')],
            'pendingBalance' => ['label' => Craft::t('kickback', 'affiliate.field.pendingBalance')],
            'payoutMethod' => ['label' => Craft::t('kickback', 'affiliate.field.payoutMethod')],
            'dateApproved' => ['label' => Craft::t('kickback', 'common.dateApproved')],
            'dateCreated' => ['label' => Craft::t('kickback', 'common.dateCreated')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'referralCode',
            'affiliateStatus',
            'lifetimeEarnings',
            'lifetimeReferrals',
            'pendingBalance',
            'dateCreated',
        ];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            [
                'label' => Craft::t('kickback', 'affiliate.field.lifetimeEarnings'),
                'orderBy' => 'kickback_affiliates.lifetimeEarnings',
                'attribute' => 'lifetimeEarnings',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('kickback', 'nav.referrals'),
                'orderBy' => 'kickback_affiliates.lifetimeReferrals',
                'attribute' => 'lifetimeReferrals',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('kickback', 'affiliate.field.pendingBalance'),
                'orderBy' => 'kickback_affiliates.pendingBalance',
                'attribute' => 'pendingBalance',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('kickback', 'common.dateApproved'),
                'orderBy' => 'kickback_affiliates.dateApproved',
                'attribute' => 'dateApproved',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['referralCode', 'paypalEmail', 'notes'];
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'lifetimeEarnings', 'pendingBalance' => Craft::$app->getFormatter()->asCurrency($this->$attribute, KickBack::getCommerceCurrency()),
            'affiliateStatus' => '<span class="status ' . $this->getStatusColor() . '"></span>' . ucfirst($this->affiliateStatus),
            'payoutMethod' => ucfirst(str_replace('_', ' ', $this->payoutMethod)),
            default => parent::attributeHtml($attribute),
        };
    }

    protected function metaFieldsHtml(bool $static): string
    {
        $statusOptions = array_map(
            static fn(string $value, array $meta): array => ['label' => $meta['label'], 'value' => $value],
            array_keys(static::statuses()),
            static::statuses(),
        );

        $payoutMethodOptions = array_map(
            static fn(string $method): array => ['label' => ucfirst(str_replace('_', ' ', $method)), 'value' => $method],
            PayoutElement::METHODS,
        );

        $currency = KickBack::getCommerceCurrency();
        $fmt = Craft::$app->getFormatter();

        $fields = [
            Cp::selectFieldHtml([
                'label' => Craft::t('kickback', 'common.status'),
                'id' => 'affiliateStatus', 'name' => 'affiliateStatus',
                'value' => $this->affiliateStatus, 'options' => $statusOptions, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'affiliate.field.referralCode'),
                'id' => 'referralCode', 'name' => 'referralCode',
                'value' => $this->referralCode, 'disabled' => $static,
            ]),
            Cp::selectFieldHtml([
                'label' => Craft::t('kickback', 'affiliate.field.payoutMethod'),
                'id' => 'payoutMethod', 'name' => 'payoutMethod',
                'value' => $this->payoutMethod, 'options' => $payoutMethodOptions, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'affiliate.field.payoutThreshold'),
                'id' => 'payoutThreshold', 'name' => 'payoutThreshold',
                'type' => 'number', 'value' => $this->payoutThreshold, 'min' => 0, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'Commission Rate Override'),
                'id' => 'commissionRateOverride', 'name' => 'commissionRateOverride',
                'type' => 'number', 'value' => $this->commissionRateOverride, 'min' => 0, 'max' => 100, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'affiliate.field.lifetimeEarnings'),
                'id' => 'lifetimeEarnings', 'name' => 'lifetimeEarnings',
                'value' => $fmt->asCurrency($this->lifetimeEarnings, $currency), 'disabled' => true,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'affiliate.field.pendingBalance'),
                'id' => 'pendingBalance', 'name' => 'pendingBalance',
                'value' => $fmt->asCurrency($this->pendingBalance, $currency), 'disabled' => true,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'Lifetime Referrals'),
                'id' => 'lifetimeReferrals', 'name' => 'lifetimeReferrals',
                'value' => $this->lifetimeReferrals, 'disabled' => true,
            ]),
        ];

        if ($this->notes !== null) {
            $fields[] = Cp::textareaFieldHtml([
                'label' => Craft::t('kickback', 'common.notes'),
                'id' => 'notes', 'name' => 'notes',
                'value' => $this->notes, 'rows' => 4, 'disabled' => $static,
            ]);
        }

        return implode("\n", $fields);
    }

    /**
     * Compute the tier level from the parent's tier level.
     */
    public static function calculateTierLevel(?int $parentTierLevel): int
    {
        return $parentTierLevel !== null ? $parentTierLevel + 1 : 1;
    }

    /**
     * Walk the parent chain from $proposedParentId upward and return true
     * if $selfId is found (i.e. a cycle exists).
     *
     * @param callable(int): ?int $getParentId Returns the parentAffiliateId for a given affiliate ID
     */
    public static function detectsCycle(int $selfId, int $proposedParentId, callable $getParentId, int $maxDepth): bool
    {
        if ($selfId === $proposedParentId) {
            return true;
        }

        $currentId = $proposedParentId;
        $steps = 0;

        while ($currentId !== null && $steps < $maxDepth) {
            $parentId = $getParentId($currentId);
            if ($parentId === $selfId) {
                return true;
            }
            $currentId = $parentId;
            $steps++;
        }

        return false;
    }

    /**
     * Return true when the parent's program does not match the child's program.
     */
    public static function parentProgramMismatch(?int $parentProgramId, ?int $childProgramId): bool
    {
        return $parentProgramId !== null && $childProgramId !== null && $parentProgramId !== $childProgramId;
    }

    /**
     * Classify whether an affiliate status is usable as a parent.
     *
     * @return 'valid'|'inactive'
     */
    public static function resolveParentStatus(string $affiliateStatus): string
    {
        return $affiliateStatus === self::STATUS_ACTIVE ? 'valid' : 'inactive';
    }

    public function beforeValidate(): bool
    {
        $this->tierLevel = $this->parentAffiliateId !== null
            ? self::calculateTierLevel(KickBack::getInstance()->affiliates->getAffiliateById($this->parentAffiliateId)?->tierLevel)
            : 1;

        return parent::beforeValidate();
    }

    public function afterSave(bool $isNew): void
    {
        $record = $isNew ? new AffiliateRecord() : (AffiliateRecord::findOne($this->id)
            ?? throw new \RuntimeException("Invalid affiliate ID: {$this->id}"));
        if ($isNew) {
            $record->id = $this->id;
        }

        foreach (['userId', 'programId', 'referralCode', 'commissionRateOverride', 'commissionTypeOverride',
            'parentAffiliateId', 'tierLevel', 'groupId', 'paypalEmail', 'stripeAccountId',
            'payoutMethod', 'payoutThreshold', 'lifetimeEarnings', 'lifetimeReferrals', 'pendingBalance', 'notes', ] as $attr) {
            $record->$attr = $this->$attr;
        }
        $record->status = $this->affiliateStatus;
        $record->dateApproved = $this->dateApproved?->format('Y-m-d H:i:s');
        $record->save(false);

        parent::afterSave($isNew);
    }

    public function getGqlTypeName(): string
    {
        return AffiliateTypeGenerator::getName();
    }

    public function afterDelete(): void
    {
        $record = AffiliateRecord::findOne($this->id);
        $record?->delete();

        parent::afterDelete();
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['userId', 'programId', 'referralCode'], 'required'];
        $rules[] = [['referralCode'], 'string', 'max' => 50];
        $rules[] = [['affiliateStatus'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_SUSPENDED, self::STATUS_REJECTED]];
        $rules[] = [['payoutMethod'], 'in', 'range' => PayoutElement::METHODS];
        $rules[] = [['paypalEmail'], 'email'];
        $rules[] = [['payoutThreshold', 'lifetimeEarnings', 'pendingBalance'], 'number', 'min' => 0];
        $rules[] = [['commissionRateOverride'], 'number', 'min' => 0, 'max' => 100];
        $rules[] = [['commissionTypeOverride'], 'in', 'range' => Commission::RATE_TYPES];
        $rules[] = [['tierLevel'], 'integer', 'min' => 1];

        $rules[] = [['parentAffiliateId'], function(string $attribute): void {
            if ($this->parentAffiliateId !== null && $this->id !== null && $this->parentAffiliateId === $this->id) {
                $this->addError($attribute, Craft::t('kickback', 'An affiliate cannot be its own parent.'));
            }
        }];

        $rules[] = [['parentAffiliateId'], function(string $attribute): void {
            if ($this->parentAffiliateId === null || $this->id === null) {
                return;
            }
            $affiliates = KickBack::getInstance()->affiliates;
            $maxDepth = KickBack::getInstance()->getSettings()->maxMlmDepth;

            $hasCycle = self::detectsCycle(
                $this->id,
                $this->parentAffiliateId,
                static fn(int $id): ?int => $affiliates->getAffiliateById($id)?->parentAffiliateId,
                $maxDepth,
            );

            if ($hasCycle) {
                $this->addError($attribute, Craft::t('kickback', 'Circular parent chain detected.'));
            }
        }];

        $rules[] = [['parentAffiliateId'], function(string $attribute): void {
            if ($this->parentAffiliateId === null || $this->programId === null) {
                return;
            }
            $parent = KickBack::getInstance()->affiliates->getAffiliateById($this->parentAffiliateId);
            if ($parent !== null && self::parentProgramMismatch($parent->programId, $this->programId)) {
                $this->addError($attribute, Craft::t('kickback', 'Parent affiliate must belong to the same program.'));
            }
        }];

        return $rules;
    }
}
