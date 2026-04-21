<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\ProgramElement;
use anvildev\craftkickback\helpers\CsvExportHelper;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\enums\PropagationMethod;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * CRUD for affiliate programs and their default commission settings.
 */
class ProgramsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(KickBack::PERMISSION_MANAGE_PROGRAMS);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('kickback/programs/index');
    }

    public function actionEdit(?int $programId = null): Response
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $primarySite = Craft::$app->getSites()->getPrimarySite();

        if ($programId === null && $currentSite->id !== $primarySite->id) {
            return $this->renderTemplate('kickback/programs/_new-requires-primary', [
                'currentSite' => $currentSite,
                'primarySite' => $primarySite,
            ]);
        }

        $program = null;

        if ($programId !== null) {
            $program = ProgramElement::find()
                ->id($programId)
                ->siteId($currentSite->id)
                ->one();

            if ($program === null) {
                throw new NotFoundHttpException('Program not found.');
            }
        }

        return $this->renderTemplate('kickback/programs/_edit', [
            'program' => $program,
            'currentSite' => $currentSite,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $programId = $request->getBodyParam('programId');

        if ($programId) {
            $program = ProgramElement::find()
                ->id((int)$programId)
                ->siteId($currentSite->id)
                ->one();

            if ($program === null) {
                throw new NotFoundHttpException('Program not found.');
            }
        } else {
            $program = new ProgramElement();
        }

        $program->siteId = $currentSite->id;

        $program->propagationMethod = PropagationMethod::tryFrom(
            (string)$request->getBodyParam('propagationMethod', 'none')
        ) ?? PropagationMethod::None;

        $program->name = $request->getRequiredBodyParam('name');
        $program->handle = $request->getRequiredBodyParam('handle');
        $program->description = $request->getBodyParam('description') ?: null;
        $program->defaultCommissionRate = (float)$request->getBodyParam('defaultCommissionRate', 10);
        $program->defaultCommissionType = $request->getBodyParam('defaultCommissionType', 'percentage');
        $program->cookieDuration = (int)$request->getBodyParam('cookieDuration', 30);
        $program->allowSelfReferral = (bool)$request->getBodyParam('allowSelfReferral');
        $program->enableCouponCreation = (bool)$request->getBodyParam('enableCouponCreation');
        $program->programStatus = $request->getBodyParam('status', 'active');
        $program->termsAndConditions = $request->getBodyParam('termsAndConditions') ?: null;

        if (!Craft::$app->getElements()->saveElement($program)) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'program.message.saveFailed'));

            Craft::$app->getUrlManager()->setRouteParams([
                'program' => $program,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('kickback', 'program.message.saved'));

        return $this->redirectToPostedUrl($program);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $programId = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (KickBack::getInstance()->programs->deleteProgramById($programId)) {
            Craft::$app->getSession()->setNotice(Craft::t('kickback', 'Program deleted.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'Couldn\'t delete program.'));
        }

        return $this->redirect('kickback/programs');
    }

    public function actionExport(): void
    {
        $status = Craft::$app->getRequest()->getQueryParam('status');
        $label = $status ?? 'all';

        CsvExportHelper::streamAsDownload(
            ['ID', 'Name', 'Handle', 'Status', 'Default Rate', 'Default Type', 'Cookie Duration', 'Date Created'],
            function(int $offset, int $limit) use ($status): array {
                $query = ProgramElement::find()
                    ->status(null)
                    ->orderBy(['elements.dateCreated' => SORT_DESC])
                    ->offset($offset)
                    ->limit($limit);

                if ($status !== null && $status !== '') {
                    $query->programStatus($status);
                }

                return array_map(fn(ProgramElement $p) => [
                    $p->id,
                    $p->name,
                    $p->handle,
                    $p->programStatus,
                    $p->defaultCommissionRate,
                    $p->defaultCommissionType,
                    $p->cookieDuration,
                    $p->dateCreated?->format('Y-m-d H:i:s'),
                ], $query->all());
            },
            'programs-' . $label . '-' . date('Y-m-d') . '.csv',
        );
    }
}
