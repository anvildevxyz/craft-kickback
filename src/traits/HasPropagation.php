<?php

declare(strict_types=1);

namespace anvildev\craftkickback\traits;

use Craft;
use craft\enums\PropagationMethod;
use craft\helpers\ArrayHelper;

/**
 * Uses a private backing field so ElementQuery can populate from raw string
 * values without TypeError on typed enum property.
 */
trait HasPropagation
{
    private PropagationMethod $_propagationMethod = PropagationMethod::None;

    public function setPropagationMethod(PropagationMethod|string|null $value): void
    {
        if ($value instanceof PropagationMethod) {
            $this->_propagationMethod = $value;
            return;
        }
        $this->_propagationMethod = PropagationMethod::tryFrom((string)($value ?? 'none'))
            ?? PropagationMethod::None;
    }

    public function getPropagationMethod(): PropagationMethod
    {
        return $this->_propagationMethod;
    }

    public function getSupportedSites(): array
    {
        $sites = Craft::$app->getSites();
        $currentSite = fn() => $sites->getSiteById($this->siteId) ?? $sites->getPrimarySite();
        return match ($this->_propagationMethod) {
            PropagationMethod::All => ArrayHelper::getColumn($sites->getAllSites(), 'id'),
            PropagationMethod::SiteGroup => ArrayHelper::getColumn($sites->getSitesByGroupId($currentSite()->groupId), 'id'),
            PropagationMethod::Language => ArrayHelper::getColumn(
                array_filter($sites->getAllSites(), fn($s) => $s->language === $currentSite()->language),
                'id'
            ),
            default => [$this->siteId ?? Craft::$app->getSites()->getPrimarySite()->id],
        };
    }
}
