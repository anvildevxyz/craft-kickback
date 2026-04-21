<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\CommissionRuleElement;
use anvildev\craftkickback\helpers\CsvExportHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\records\CommissionRuleRecord;
use Craft;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * CRUD for program-specific commission rules (product, category, tiered,
 * bonus, MLM).
 */
class CommissionRulesController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(KickBack::PERMISSION_MANAGE_COMMISSIONS);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('kickback/commission-rules/index');
    }

    public function actionEdit(?int $ruleId = null): Response
    {
        $rule = null;

        if ($ruleId !== null) {
            $rule = CommissionRuleElement::find()->id($ruleId)->one();

            if ($rule === null) {
                throw new NotFoundHttpException('Commission rule not found.');
            }
        }

        $programs = KickBack::getInstance()->programs->getAllPrograms();

        return $this->renderTemplate('kickback/commission-rules/_edit', [
            'rule' => $rule,
            'programs' => $programs,
            'ruleTypes' => [
                CommissionRuleElement::TYPE_PRODUCT => Craft::t('kickback', 'rule.type.product'),
                CommissionRuleElement::TYPE_CATEGORY => Craft::t('kickback', 'rule.type.category'),
                CommissionRuleElement::TYPE_TIERED => Craft::t('kickback', 'rule.type.tiered'),
                CommissionRuleElement::TYPE_BONUS => Craft::t('kickback', 'rule.type.bonus'),
                CommissionRuleElement::TYPE_MLM_TIER => Craft::t('kickback', 'rule.type.mlmTier'),
            ],
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $ruleId = $request->getBodyParam('ruleId');

        if ($ruleId) {
            $rule = CommissionRuleElement::find()->id((int)$ruleId)->one();

            if ($rule === null) {
                throw new NotFoundHttpException('Commission rule not found.');
            }
        } else {
            $rule = new CommissionRuleElement();
        }

        $rule->programId = (int)$request->getRequiredBodyParam('programId');
        $rule->name = $request->getRequiredBodyParam('name');
        $rule->type = $request->getRequiredBodyParam('type');
        $rule->commissionRate = (float)$request->getRequiredBodyParam('commissionRate');
        $rule->commissionType = $request->getBodyParam('commissionType', Commission::RATE_TYPE_PERCENTAGE);
        $rule->priority = (int)$request->getBodyParam('priority', 0);

        foreach (['targetId', 'tierThreshold', 'tierLevel', 'lookbackDays'] as $field) {
            $v = $request->getBodyParam($field);
            $rule->$field = ($v !== null && $v !== '') ? (int)$v : null;
        }

        if (!Craft::$app->getElements()->saveElement($rule)) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'rule.message.saveFailed'));

            Craft::$app->getUrlManager()->setRouteParams([
                'rule' => $rule,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('kickback', 'rule.message.saved'));

        return $this->redirectToPostedUrl($rule);
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $ruleId = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Craft::$app->getElements()->deleteElementById($ruleId)) {
            Craft::$app->getSession()->setNotice(Craft::t('kickback', 'Commission rule deleted.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'rule.message.deleteFailed'));
        }

        return $this->redirect('kickback/commission-rules');
    }

    public function actionExport(): void
    {
        CsvExportHelper::streamAsDownload(
            ['ID', 'Name', 'Priority', 'Type', 'Target ID', 'Commission Rate', 'Commission Type', 'Tier Level', 'Date Created'],
            function(int $offset, int $limit): array {
                /** @var CommissionRuleRecord[] $records */
                $records = CommissionRuleRecord::find()
                    ->orderBy(['dateCreated' => SORT_DESC])
                    ->offset($offset)
                    ->limit($limit)
                    ->all();

                return array_map(fn($r) => [
                    $r->id,
                    $r->name,
                    $r->priority,
                    $r->type,
                    $r->targetId,
                    $r->commissionRate,
                    $r->commissionType,
                    $r->tierLevel,
                    $r->dateCreated,
                ], $records);
            },
            'commission-rules-all-' . date('Y-m-d') . '.csv',
        );
    }
}
