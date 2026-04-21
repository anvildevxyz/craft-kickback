<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements;

use anvildev\craftkickback\elements\db\ProgramQuery;
use anvildev\craftkickback\enums\ProgramStatus;
use anvildev\craftkickback\gql\types\generators\ProgramTypeGenerator;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\records\ProgramRecord;
use anvildev\craftkickback\records\ProgramSiteRecord;
use anvildev\craftkickback\traits\HasPropagation;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;

/**
 * @property \craft\enums\PropagationMethod $propagationMethod
 */
class ProgramElement extends Element
{
    use HasPropagation;

    public const STATUS_ACTIVE = ProgramStatus::Active->value;
    public const STATUS_INACTIVE = ProgramStatus::Inactive->value;
    public const STATUS_ARCHIVED = ProgramStatus::Archived->value;

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    public string $name = '';
    public string $handle = '';
    public ?string $description = null;
    public float $defaultCommissionRate = 10.0;
    public string $defaultCommissionType = 'percentage';
    public int $cookieDuration = 30;
    public bool $allowSelfReferral = false;
    public bool $enableCouponCreation = true;
    public string $programStatus = self::STATUS_ACTIVE;
    public ?string $termsAndConditions = null;

    public function getGqlTypeName(): string
    {
        return ProgramTypeGenerator::getName();
    }

    public static function displayName(): string
    {
        return Craft::t('kickback', 'common.program');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('kickback', 'nav.programs');
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

    /**
     * name, description, and termsAndConditions are per-site translatable
     * via kickback_programs_sites. Sibling-site behavior is controlled by
     * $propagationMethod (see HasPropagation).
     */
    public static function isLocalized(): bool
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
            self::STATUS_INACTIVE => [
                'label' => Craft::t('kickback', 'programStatus.inactive'),
                'color' => 'light',
            ],
            self::STATUS_ARCHIVED => [
                'label' => Craft::t('kickback', 'programStatus.archived'),
                'color' => 'light',
            ],
        ];
    }

    public function getStatus(): ?string
    {
        return $this->programStatus;
    }

    public static function find(): ProgramQuery
    {
        return new ProgramQuery(static::class);
    }

    public function beforeValidate(): bool
    {
        $this->title = $this->name;
        return parent::beforeValidate();
    }

    protected function cpEditUrl(): ?string
    {
        $site = Craft::$app->getSites()->getCurrentSite();
        return UrlHelper::cpUrl("kickback/programs/{$this->id}", ['site' => $site->handle]);
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('kickback/programs');
    }

    public function canView(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_PROGRAMS);
    }

    public function canSave(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_PROGRAMS);
    }

    public function canDelete(User $user): bool
    {
        return $user->can(KickBack::PERMISSION_MANAGE_PROGRAMS);
    }

    protected static function defineSources(?string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('kickback', 'All Programs'),
                'criteria' => [],
                'defaultSort' => ['title', 'asc'],
            ],
            [
                'heading' => Craft::t('kickback', 'common.status'),
            ],
            [
                'key' => 'status:active',
                'label' => Craft::t('kickback', 'affiliateStatus.active'),
                'criteria' => ['programStatus' => self::STATUS_ACTIVE],
            ],
            [
                'key' => 'status:inactive',
                'label' => Craft::t('kickback', 'programStatus.inactive'),
                'criteria' => ['programStatus' => self::STATUS_INACTIVE],
            ],
            [
                'key' => 'status:archived',
                'label' => Craft::t('kickback', 'programStatus.archived'),
                'criteria' => ['programStatus' => self::STATUS_ARCHIVED],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'handle' => ['label' => Craft::t('kickback', 'common.handle')],
            'defaultCommissionRate' => ['label' => Craft::t('kickback', 'common.commission')],
            'programStatus' => ['label' => Craft::t('kickback', 'common.status')],
            'cookieDuration' => ['label' => Craft::t('kickback', 'Cookie Duration')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'handle',
            'defaultCommissionRate',
            'programStatus',
            'dateCreated',
        ];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            [
                'label' => Craft::t('kickback', 'group.field.commissionRate'),
                'orderBy' => 'kickback_programs.defaultCommissionRate',
                'attribute' => 'defaultCommissionRate',
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
        return ['name', 'handle', 'description'];
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'defaultCommissionRate' => $this->defaultCommissionType === 'percentage'
                ? $this->defaultCommissionRate . '%'
                : Craft::$app->getFormatter()->asCurrency($this->defaultCommissionRate, KickBack::getCommerceCurrency()),
            'programStatus' => '<span class="status ' . (static::statuses()[$this->programStatus]['color'] ?? '') . '"></span>' . ucfirst($this->programStatus),
            'handle' => '<code>' . $this->handle . '</code>',
            'cookieDuration' => $this->cookieDuration . ' ' . Craft::t('kickback', 'days'),
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

        $commissionTypeOptions = array_map(
            static fn(string $type): array => ['label' => ucfirst($type), 'value' => $type],
            Commission::RATE_TYPES,
        );

        return implode("\n", [
            Cp::selectFieldHtml([
                'label' => Craft::t('kickback', 'common.status'),
                'id' => 'programStatus', 'name' => 'programStatus',
                'value' => $this->programStatus, 'options' => $statusOptions, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'common.handle'),
                'id' => 'handle', 'name' => 'handle',
                'value' => $this->handle, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'settings.defaultRate'),
                'id' => 'defaultCommissionRate', 'name' => 'defaultCommissionRate',
                'type' => 'number', 'value' => $this->defaultCommissionRate, 'min' => 0, 'disabled' => $static,
            ]),
            Cp::selectFieldHtml([
                'label' => Craft::t('kickback', 'Commission Type'),
                'id' => 'defaultCommissionType', 'name' => 'defaultCommissionType',
                'value' => $this->defaultCommissionType, 'options' => $commissionTypeOptions, 'disabled' => $static,
            ]),
            Cp::textFieldHtml([
                'label' => Craft::t('kickback', 'settings.cookieDuration'),
                'id' => 'cookieDuration', 'name' => 'cookieDuration',
                'type' => 'number', 'value' => $this->cookieDuration, 'min' => 1, 'disabled' => $static,
            ]),
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('kickback', 'program.field.allowSelfReferral'),
                'id' => 'allowSelfReferral', 'name' => 'allowSelfReferral',
                'on' => $this->allowSelfReferral, 'disabled' => $static,
            ]),
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('kickback', 'Enable Coupon Creation'),
                'id' => 'enableCouponCreation', 'name' => 'enableCouponCreation',
                'on' => $this->enableCouponCreation, 'disabled' => $static,
            ]),
        ]);
    }

    public function afterSave(bool $isNew): void
    {
        // Global record is owned by the origin save only.
        if (!$this->propagating) {
            $this->saveProgramRecord($isNew);
        }

        // Site record is written on every pass (origin + propagation siblings)
        // so PropagationMethod::All/SiteGroup/Language actually copies values.
        $this->saveSiteRecord();

        parent::afterSave($isNew);
    }

    private function saveProgramRecord(bool $isNew): void
    {
        $record = $isNew ? new ProgramRecord() : (ProgramRecord::findOne($this->id)
            ?? throw new \RuntimeException("Invalid program ID: {$this->id}"));
        if ($isNew) {
            $record->id = $this->id;
        }

        foreach (['handle', 'defaultCommissionRate', 'defaultCommissionType',
            'cookieDuration', 'allowSelfReferral', 'enableCouponCreation', ] as $attr) {
            $record->$attr = $this->$attr;
        }
        $record->propagationMethod = $this->propagationMethod->value;
        $record->status = $this->programStatus;
        $record->save(false);
    }

    private function saveSiteRecord(): void
    {
        if (empty($this->siteId)) {
            throw new \RuntimeException(
                "Cannot save ProgramSiteRecord: ProgramElement::\$siteId is not set. " .
                "Set the element's siteId before calling saveElement()."
            );
        }

        $siteRecord = ProgramSiteRecord::findOne([
            'id' => $this->id,
            'siteId' => $this->siteId,
        ]);

        if ($siteRecord === null) {
            $siteRecord = new ProgramSiteRecord();
            $siteRecord->id = $this->id;
            $siteRecord->siteId = $this->siteId;
        }

        $siteRecord->name = $this->name;
        $siteRecord->description = $this->description;
        $siteRecord->termsAndConditions = $this->termsAndConditions;
        $siteRecord->save(false);
    }

    public function afterDelete(): void
    {
        $record = ProgramRecord::findOne($this->id);
        $record?->delete();

        parent::afterDelete();
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name'], 'string', 'max' => 255];
        $rules[] = [['handle'], 'string', 'max' => 64];
        $rules[] = [['handle'], 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_]*$/'];
        $rules[] = [['handle'], 'unique', 'targetClass' => ProgramRecord::class, 'targetAttribute' => 'handle',
            'filter' => fn($query) => $this->id ? $query->andWhere(['!=', 'id', $this->id]) : $query,
            'message' => \Craft::t('kickback', '{attribute} "{value}" has already been taken.'), ];
        $rules[] = [['defaultCommissionRate'], 'number', 'min' => 0];
        $rules[] = [['defaultCommissionType'], 'in', 'range' => Commission::RATE_TYPES];
        $rules[] = [['cookieDuration'], 'integer', 'min' => 1];
        $rules[] = [['programStatus'], 'in', 'range' => self::STATUSES];

        return $rules;
    }
}
