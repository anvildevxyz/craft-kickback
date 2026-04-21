<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\db;

use anvildev\craftkickback\elements\ProgramElement;
use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @method ProgramElement|null one($db = null)
 * @method ProgramElement[] all($db = null)
 *
 * @extends ElementQuery<int, ProgramElement>
 */
class ProgramQuery extends ElementQuery
{
    public ?string $handle = null;
    public ?string $programStatus = null;

    public function handle(?string $value): self
    {
        $this->handle = $value;
        return $this;
    }

    public function programStatus(?string $value): self
    {
        $this->programStatus = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('kickback_programs');

        $currentSiteId = $this->siteId ?: Craft::$app->getSites()->getCurrentSite()->id;
        $currentSiteId = (int)(is_array($currentSiteId)
            ? ($currentSiteId[0] ?? Craft::$app->getSites()->getPrimarySite()->id)
            : $currentSiteId);

        $this->query->select([
            'kickback_programs.handle',
            'kickback_programs.defaultCommissionRate',
            'kickback_programs.defaultCommissionType',
            'kickback_programs.cookieDuration',
            'kickback_programs.allowSelfReferral',
            'kickback_programs.enableCouponCreation',
            'kickback_programs.propagationMethod',
            'kickback_programs.status as programStatus',
            'kickback_programs_sites.name',
            'kickback_programs_sites.description',
            'kickback_programs_sites.termsAndConditions',
        ]);

        $this->query->leftJoin(
            '{{%kickback_programs_sites}} kickback_programs_sites',
            '[[kickback_programs_sites.id]] = [[kickback_programs.id]] AND [[kickback_programs_sites.siteId]] = :currentSiteId',
            [':currentSiteId' => $currentSiteId],
        );

        foreach (['handle' => $this->handle, 'status' => $this->programStatus] as $col => $val) {
            if ($val !== null) {
                $this->subQuery->andWhere(Db::parseParam("kickback_programs.$col", $val));
            }
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        $map = [
            ProgramElement::STATUS_ACTIVE => 'active',
            ProgramElement::STATUS_INACTIVE => 'inactive',
            ProgramElement::STATUS_ARCHIVED => 'archived',
        ];
        return isset($map[$status])
            ? ['kickback_programs.status' => $map[$status]]
            : parent::statusCondition($status);
    }
}
