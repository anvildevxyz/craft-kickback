<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\ProgramElement;
use anvildev\craftkickback\events\ProgramEvent;
use anvildev\craftkickback\models\Commission;
use Craft;
use craft\base\Component;

/**
 * Manages affiliate programs and their default commission settings.
 */
class ProgramService extends Component
{
    public const EVENT_BEFORE_SAVE_PROGRAM = 'beforeSaveProgram';
    public const EVENT_AFTER_SAVE_PROGRAM = 'afterSaveProgram';
    public const EVENT_BEFORE_DELETE_PROGRAM = 'beforeDeleteProgram';
    public const EVENT_AFTER_DELETE_PROGRAM = 'afterDeleteProgram';

    public function getProgramById(int $id): ?ProgramElement
    {
        return ProgramElement::find()->id($id)->one();
    }

    public function getProgramByHandle(string $handle): ?ProgramElement
    {
        return ProgramElement::find()->handle($handle)->one();
    }

    public function getDefaultProgram(): ?ProgramElement
    {
        return ProgramElement::find()
            ->programStatus(ProgramElement::STATUS_ACTIVE)
            ->orderBy(['elements.dateCreated' => SORT_ASC])
            ->one();
    }

    /**
     * @return ProgramElement[]
     */
    public function getAllPrograms(): array
    {
        return ProgramElement::find()->orderBy(['title' => SORT_ASC])->all();
    }

    public function saveProgram(ProgramElement $program): bool
    {
        $payload = ['program' => $program, 'isNew' => !$program->id];

        $this->trigger(self::EVENT_BEFORE_SAVE_PROGRAM, $event = new ProgramEvent($payload));
        if (!$event->isValid || !Craft::$app->getElements()->saveElement($program)) {
            return false;
        }

        $this->trigger(self::EVENT_AFTER_SAVE_PROGRAM, new ProgramEvent($payload));

        return true;
    }

    /**
     * Delete a program. Refuses deletion if active affiliates still exist.
     */
    public function deleteProgramById(int $id): bool
    {
        $program = ProgramElement::find()->id($id)->one();
        if ($program === null) {
            return false;
        }

        $affiliateCount = AffiliateElement::find()
            ->programId($id)
            ->affiliateStatus(AffiliateElement::STATUS_ACTIVE)
            ->count();

        if ($affiliateCount > 0) {
            Craft::warning("Cannot delete program #{$id}: {$affiliateCount} active affiliates exist", __METHOD__);
            return false;
        }

        $this->trigger(self::EVENT_BEFORE_DELETE_PROGRAM, $event = new ProgramEvent(['program' => $program]));
        if (!$event->isValid) {
            return false;
        }

        if ($result = Craft::$app->getElements()->deleteElementById($id)) {
            $this->trigger(self::EVENT_AFTER_DELETE_PROGRAM, new ProgramEvent(['program' => $program]));
        }

        return $result;
    }

    public function createDefaultProgram(): ProgramElement
    {
        $program = new ProgramElement();
        $program->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $program->name = 'Default';
        $program->handle = 'default';
        $program->description = 'Default affiliate program';
        $program->defaultCommissionRate = 10.0;
        $program->defaultCommissionType = Commission::RATE_TYPE_PERCENTAGE;
        $program->programStatus = ProgramElement::STATUS_ACTIVE;

        $this->saveProgram($program);

        return $program;
    }
}
