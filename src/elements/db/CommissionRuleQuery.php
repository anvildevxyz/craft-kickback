<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\db;

use anvildev\craftkickback\elements\CommissionRuleElement;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @method CommissionRuleElement|null one($db = null)
 * @method CommissionRuleElement[] all($db = null)
 *
 * @extends ElementQuery<int, CommissionRuleElement>
 */
class CommissionRuleQuery extends ElementQuery
{
    /**
     * Declaring this real class-level property (not assigning it at
     * runtime) is what makes Craft 6 auto-join this table — it replaces
     * joinElementTable(), which beforePrepare() below still calls for
     * Craft 5. Craft 5's own ElementQuery has no such property, so this
     * is just an unused extra property there.
     */
    protected string $table = 'kickback_commission_rules';

    public ?int $programId = null;
    public ?string $type = null;
    public ?int $targetId = null;
    public ?int $tierLevel = null;

    public function programId(?int $value): self
    {
        $this->programId = $value;
        return $this;
    }

    public function type(?string $value): self
    {
        $this->type = $value;
        return $this;
    }

    public function targetId(?int $value): self
    {
        $this->targetId = $value;
        return $this;
    }

    public function tierLevel(?int $value): self
    {
        $this->tierLevel = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('kickback_commission_rules');

        $this->query->select([
            'kickback_commission_rules.programId',
            'kickback_commission_rules.name',
            'kickback_commission_rules.type',
            'kickback_commission_rules.targetId',
            'kickback_commission_rules.commissionRate',
            'kickback_commission_rules.commissionType',
            'kickback_commission_rules.tierThreshold',
            'kickback_commission_rules.tierLevel',
            'kickback_commission_rules.lookbackDays',
            'kickback_commission_rules.priority',
            'kickback_commission_rules.conditions',
        ]);

        $params = [
            'programId' => $this->programId,
            'type' => $this->type,
            'targetId' => $this->targetId,
            'tierLevel' => $this->tierLevel,
        ];
        foreach ($params as $col => $val) {
            if ($val !== null) {
                $this->subQuery->andWhere(Db::parseParam("kickback_commission_rules.$col", $val));
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
            'kickback_commission_rules.programId',
            'kickback_commission_rules.name',
            'kickback_commission_rules.type',
            'kickback_commission_rules.targetId',
            'kickback_commission_rules.commissionRate',
            'kickback_commission_rules.commissionType',
            'kickback_commission_rules.tierThreshold',
            'kickback_commission_rules.tierLevel',
            'kickback_commission_rules.lookbackDays',
            'kickback_commission_rules.priority',
            'kickback_commission_rules.conditions',
        ]);

        $params = [
            'programId' => $this->programId,
            'type' => $this->type,
            'targetId' => $this->targetId,
            'tierLevel' => $this->tierLevel,
        ];
        foreach ($params as $col => $val) {
            if ($val !== null) {
                $this->query->whereParam("kickback_commission_rules.$col", $val);
            }
        }
    }
}
