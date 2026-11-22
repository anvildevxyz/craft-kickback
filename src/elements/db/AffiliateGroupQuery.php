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
    /**
     * Declaring this real class-level property (not assigning it at
     * runtime) is what makes Craft 6 auto-join this table — it replaces
     * joinElementTable(), which beforePrepare() below still calls for
     * Craft 5. Craft 5's own ElementQuery has no such property, so this
     * is just an unused extra property there.
     */
    protected string $table = 'kickback_affiliate_groups';

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

    /**
     * Craft 6 never calls beforePrepare() at all — this is the real hook
     * there. Translates the same select/filters above to the new
     * Illuminate\Database\Query\Builder ($this->query is no longer split
     * into query/subQuery, and has no andWhere()/joinElementTable()).
     */
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
            'kickback_affiliate_groups.name',
            'kickback_affiliate_groups.handle',
            'kickback_affiliate_groups.commissionRate',
            'kickback_affiliate_groups.commissionType',
            'kickback_affiliate_groups.sortOrder',
        ]);

        foreach (['handle' => $this->handle, 'commissionType' => $this->commissionType] as $col => $val) {
            if ($val !== null) {
                $this->query->whereParam("kickback_affiliate_groups.$col", $val);
            }
        }
    }
}
