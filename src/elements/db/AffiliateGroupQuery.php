<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\db;

use anvildev\craftkickback\elements\AffiliateGroupElement;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @method AffiliateGroupElement|null one($db = null)
 * @method AffiliateGroupElement[] all($db = null)
 *
 * @extends ElementQuery<int, AffiliateGroupElement>
 */
class AffiliateGroupQuery extends ElementQuery
{
    public ?string $handle = null;
    public ?string $commissionType = null;

    public function handle(?string $value): self
    {
        $this->handle = $value;
        return $this;
    }

    public function commissionType(?string $value): self
    {
        $this->commissionType = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('kickback_affiliate_groups');

        $this->query->select([
            'kickback_affiliate_groups.name',
            'kickback_affiliate_groups.handle',
            'kickback_affiliate_groups.commissionRate',
            'kickback_affiliate_groups.commissionType',
            'kickback_affiliate_groups.sortOrder',
        ]);

        foreach (['handle' => $this->handle, 'commissionType' => $this->commissionType] as $col => $val) {
            if ($val !== null) {
                $this->subQuery->andWhere(Db::parseParam("kickback_affiliate_groups.$col", $val));
            }
        }

        return parent::beforePrepare();
    }
}
