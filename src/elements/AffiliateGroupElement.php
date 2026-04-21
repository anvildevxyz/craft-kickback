<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements;

use anvildev\craftkickback\elements\db\AffiliateGroupQuery;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\records\AffiliateGroupRecord;
use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\helpers\UrlHelper;

class AffiliateGroupElement extends Element
{
    public string $name = '';
    public string $handle = '';
    public float $commissionRate = 0.0;
    public string $commissionType = Commission::RATE_TYPE_PERCENTAGE;
    public int $sortOrder = 0;

    public static function displayName(): string
    {
        return Craft::t('kickback', 'Affiliate Group');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('kickback', 'nav.affiliateGroups');
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

    public static function find(): AffiliateGroupQuery
    {
        return new AffiliateGroupQuery(static::class);
    }

    public function getGqlTypeName(): string
    {
        return \anvildev\craftkickback\gql\types\generators\AffiliateGroupTypeGenerator::getName();
    }

    public function beforeValidate(): bool
    {
        $this->title = $this->name;
        return parent::beforeValidate();
    }

    protected function cpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("kickback/affiliate-groups/{$this->id}");
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('kickback/affiliate-groups');
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
                'label' => Craft::t('kickback', 'All Groups'),
                'criteria' => [],
                'defaultSort' => ['sortOrder', 'asc'],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'handle' => ['label' => Craft::t('kickback', 'common.handle')],
            'commissionRate' => ['label' => Craft::t('kickback', 'group.field.commissionRate')],
            'commissionType' => ['label' => Craft::t('kickback', 'Commission Type')],
            'sortOrder' => ['label' => Craft::t('kickback', 'common.sortOrder')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'handle',
            'commissionRate',
            'sortOrder',
            'dateCreated',
        ];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            [
                'label' => Craft::t('kickback', 'common.sortOrder'),
                'orderBy' => 'kickback_affiliate_groups.sortOrder',
                'attribute' => 'sortOrder',
                'defaultDir' => 'asc',
            ],
            [
                'label' => Craft::t('kickback', 'group.field.commissionRate'),
                'orderBy' => 'kickback_affiliate_groups.commissionRate',
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
        return ['name', 'handle'];
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'commissionRate' => $this->commissionType === 'percentage'
                ? $this->commissionRate . '%'
                : Craft::$app->getFormatter()->asCurrency($this->commissionRate, KickBack::getCommerceCurrency()),
            'commissionType' => ucfirst($this->commissionType),
            'handle' => '<code>' . $this->handle . '</code>',
            default => parent::attributeHtml($attribute),
        };
    }

    public function afterSave(bool $isNew): void
    {
        $record = $isNew ? new AffiliateGroupRecord() : (AffiliateGroupRecord::findOne($this->id)
            ?? throw new \RuntimeException("Invalid affiliate group ID: {$this->id}"));
        if ($isNew) {
            $record->id = $this->id;
        }

        foreach (['name', 'handle', 'commissionRate', 'commissionType', 'sortOrder'] as $attr) {
            $record->$attr = $this->$attr;
        }
        $record->save(false);

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        $record = AffiliateGroupRecord::findOne($this->id);
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
        $rules[] = [['commissionRate'], 'required'];
        $rules[] = [['commissionRate'], 'number', 'min' => 0];
        $rules[] = [['commissionType'], 'in', 'range' => Commission::RATE_TYPES];
        $rules[] = [['sortOrder'], 'integer'];

        return $rules;
    }
}
