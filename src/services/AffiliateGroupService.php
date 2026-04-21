<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateGroupElement;
use craft\base\Component;

/**
 * Manages affiliate groups and their commission rate overrides.
 */
class AffiliateGroupService extends Component
{
    public function getGroupById(int $id): ?AffiliateGroupElement
    {
        return AffiliateGroupElement::find()->id($id)->one();
    }

    public function getGroupByHandle(string $handle): ?AffiliateGroupElement
    {
        return AffiliateGroupElement::find()->handle($handle)->one();
    }

    /**
     * @return AffiliateGroupElement[]
     */
    public function getAllGroups(): array
    {
        return AffiliateGroupElement::find()
            ->orderBy(['kickback_affiliate_groups.sortOrder' => SORT_ASC, 'title' => SORT_ASC])
            ->all();
    }
}
