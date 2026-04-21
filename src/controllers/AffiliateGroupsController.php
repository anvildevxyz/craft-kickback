<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\AffiliateGroupElement;
use anvildev\craftkickback\helpers\CsvExportHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\AffiliateGroupRecord;
use Craft;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * CRUD for affiliate groups with shared commission rates.
 */
class AffiliateGroupsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(KickBack::PERMISSION_MANAGE_AFFILIATES);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('kickback/affiliate-groups/index');
    }

    public function actionEdit(?int $groupId = null): Response
    {
        $group = null;

        if ($groupId !== null) {
            $group = AffiliateGroupElement::find()->id($groupId)->one();

            if ($group === null) {
                throw new NotFoundHttpException('Affiliate group not found.');
            }
        }

        return $this->renderTemplate('kickback/affiliate-groups/_edit', [
            'group' => $group,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $groupId = $request->getBodyParam('groupId');

        if ($groupId) {
            $group = AffiliateGroupElement::find()->id((int)$groupId)->one();

            if ($group === null) {
                throw new NotFoundHttpException('Affiliate group not found.');
            }
        } else {
            $group = new AffiliateGroupElement();
        }

        $group->name = $request->getRequiredBodyParam('name');
        $group->handle = $request->getRequiredBodyParam('handle');
        $group->commissionRate = (float)$request->getRequiredBodyParam('commissionRate');
        $group->commissionType = $request->getBodyParam('commissionType', 'percentage');
        $group->sortOrder = (int)$request->getBodyParam('sortOrder', 0);

        if (!Craft::$app->getElements()->saveElement($group)) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'group.message.saveFailed'));

            Craft::$app->getUrlManager()->setRouteParams([
                'group' => $group,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('kickback', 'group.message.saved'));

        return $this->redirectToPostedUrl($group);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $groupId = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Craft::$app->getElements()->deleteElementById($groupId)) {
            Craft::$app->getSession()->setNotice(Craft::t('kickback', 'Affiliate group deleted.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'group.message.deleteFailed'));
        }

        return $this->redirect('kickback/affiliate-groups');
    }

    public function actionExport(): void
    {
        CsvExportHelper::streamAsDownload(
            ['ID', 'Name', 'Handle', 'Commission Rate', 'Commission Type', 'Date Created'],
            function(int $offset, int $limit): array {
                /** @var AffiliateGroupRecord[] $records */
                $records = AffiliateGroupRecord::find()
                    ->orderBy(['dateCreated' => SORT_DESC])
                    ->offset($offset)
                    ->limit($limit)
                    ->all();

                return array_map(fn($r) => [
                    $r->id,
                    $r->name,
                    $r->handle,
                    $r->commissionRate,
                    $r->commissionType,
                    $r->dateCreated,
                ], $records);
            },
            'affiliate-groups-all-' . date('Y-m-d') . '.csv',
        );
    }
}
