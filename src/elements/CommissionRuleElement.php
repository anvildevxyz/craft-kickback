<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements;

use anvildev\craftkickback\elements\db\CommissionRuleQuery;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\records\CommissionRuleRecord;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;

class CommissionRuleElement extends Element
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_TIERED = 'tiered';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_MLM_TIER = 'mlm_tier';

    public const TYPES = [
        self::TYPE_PRODUCT,
        self::TYPE_CATEGORY,
        self::TYPE_TIERED,
        self::TYPE_BONUS,
        self::TYPE_MLM_TIER,
    ];

    public ?int $programId = null;
    public string $name = '';
    public string $type = '';
    public ?int $targetId = null;
    public float $commissionRate = 0.0;
    public string $commissionType = 'percentage';
    public ?int $tierThreshold = null;
    public ?int $tierLevel = null;
    public ?int $lookbackDays = null;
    public int $priority = 0;
    public ?string $conditions = null;

    public static function displayName(): string
    {
        return Craft::t('kickback', 'Commission Rule');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('kickback', 'nav.commissionRules');
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
        return false;
    }

    public static function find(): CommissionRuleQuery
    {
        return new CommissionRuleQuery(static::class);
    }

    public function getGqlTypeName(): string
    {
        return \anvildev\craftkickback\gql\types\generators\CommissionRuleTypeGenerator::getName();
    }

    public function beforeValidate(): bool
    {
        $this->title = $this->name;
        return parent::beforeValidate();
    }

    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("kickback/commission-rules/{$this->id}");
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('kickback/commission-rules');
    }

    public function canView(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_COMMISSIONS);
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
                'label' => Craft::t('kickback', 'All Rules'),
                'criteria' => [],
                'defaultSort' => ['priority', 'desc'],
            ],
            ['heading' => Craft::t('kickback', 'Type')],
            [
                'key' => 'type:product',
                'label' => Craft::t('kickback', 'rule.type.product'),
                'criteria' => ['type' => self::TYPE_PRODUCT],
            ],
            [
                'key' => 'type:category',
                'label' => Craft::t('kickback', 'rule.type.category'),
                'criteria' => ['type' => self::TYPE_CATEGORY],
            ],
            [
                'key' => 'type:tiered',
                'label' => Craft::t('kickback', 'rule.type.tiered'),
                'criteria' => ['type' => self::TYPE_TIERED],
            ],
            [
                'key' => 'type:bonus',
                'label' => Craft::t('kickback', 'rule.type.bonus'),
                'criteria' => ['type' => self::TYPE_BONUS],
            ],
            [
                'key' => 'type:mlm_tier',
                'label' => Craft::t('kickback', 'rule.type.mlmTier'),
                'criteria' => ['type' => self::TYPE_MLM_TIER],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'type' => ['label' => Craft::t('kickback', 'Type')],
            'programId' => ['label' => Craft::t('kickback', 'common.program')],
            'commissionRate' => ['label' => Craft::t('kickback', 'group.field.commissionRate')],
            'priority' => ['label' => Craft::t('kickback', 'common.priority')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'type',
            'commissionRate',
            'priority',
            'dateCreated',
        ];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            [
                'label' => Craft::t('kickback', 'common.priority'),
                'orderBy' => 'kickback_commission_rules.priority',
                'attribute' => 'priority',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('kickback', 'group.field.commissionRate'),
                'orderBy' => 'kickback_commission_rules.commissionRate',
                'attribute' => 'commissionRate',
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
        return ['name'];
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'type' => ucfirst(str_replace('_', ' ', $this->type)),
            'commissionRate' => $this->commissionType === 'percentage'
                ? $this->commissionRate . '%'
                : Craft::$app->getFormatter()->asCurrency($this->commissionRate, KickBack::getCommerceCurrency()),
            'priority' => (string)$this->priority,
            default => parent::attributeHtml($attribute),
        };
    }

    protected function metaFieldsHtml(bool $static): string
    {
        $typeOptions = array_map(
            static fn(string $type): array => ['label' => ucfirst(str_replace('_', ' ', $type)), 'value' => $type],
            self::TYPES,
        );

        $commissionTypeOptions = array_map(
            static fn(string $type): array => ['label' => ucfirst($type), 'value' => $type],
            Commission::RATE_TYPES,
        );

        $fields = [
            Cp::selectFieldHtml([
                'label' => Craft::t('kickback', 'rule.field.type'),
                'id' => 'type', 'name' => 'type',
                'value' => $this->type, 'options' => $typeOptions, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'group.field.commissionRate'),
                'id' => 'commissionRate', 'name' => 'commissionRate',
                'type' => 'number', 'value' => $this->commissionRate, 'min' => 0, 'disabled' => $static,
            ]),
            Cp::selectFieldHtml([
                'label' => Craft::t('kickback', 'Commission Type'),
                'id' => 'commissionType', 'name' => 'commissionType',
                'value' => $this->commissionType, 'options' => $commissionTypeOptions, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'common.priority'),
                'id' => 'priority', 'name' => 'priority',
                'type' => 'number', 'value' => $this->priority, 'disabled' => $static,
            ]),
        ];

        foreach (['tierThreshold' => 'Tier Threshold', 'tierLevel' => 'Tier Level', 'lookbackDays' => 'Lookback Days'] as $attr => $label) {
            if ($this->$attr !== null) {
                $fields[] = Cp::textFieldHtml([
                    'label' => Craft::t('kickback', $label),
                    'id' => $attr, 'name' => $attr,
                    'type' => 'number', 'value' => $this->$attr, 'disabled' => $static,
                ]);
            }
        }

        return implode("\n", $fields);
    }

    public function afterSave(bool $isNew): void
    {
        $record = $isNew ? new CommissionRuleRecord() : (CommissionRuleRecord::findOne($this->id)
            ?? throw new \RuntimeException("Invalid commission rule ID: {$this->id}"));
        if ($isNew) {
            $record->id = $this->id;
        }

        foreach (['programId', 'name', 'type', 'targetId', 'commissionRate', 'commissionType',
            'tierThreshold', 'tierLevel', 'lookbackDays', 'priority', 'conditions', ] as $attr) {
            $record->$attr = $this->$attr;
        }
        $record->save(false);

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        $record = CommissionRuleRecord::findOne($this->id);
        $record?->delete();

        parent::afterDelete();
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'type', 'programId', 'commissionRate'], 'required'];
        $rules[] = [['name'], 'string', 'max' => 255];
        $rules[] = [['type'], 'in', 'range' => self::TYPES];
        $rules[] = [['programId', 'targetId', 'tierThreshold', 'tierLevel', 'lookbackDays'], 'integer'];
        $rules[] = [['commissionRate'], 'number', 'min' => 0];
        $rules[] = [['commissionType'], 'in', 'range' => Commission::RATE_TYPES];
        $rules[] = [['priority'], 'integer'];

        return $rules;
    }
}
