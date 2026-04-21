<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\exceptions\ApprovalAlreadyResolvedException;
use anvildev\craftkickback\exceptions\ApprovalNotFoundException;
use anvildev\craftkickback\exceptions\ApprovalTargetMissingException;
use anvildev\craftkickback\exceptions\SelfVerificationException;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\ApprovalRecord;
use Craft;
use craft\elements\User;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Endpoints for the payout verification queue and approve/reject actions.
 */
class ApprovalsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Permission check at the HTTP boundary; the self-verify rule is
        // enforced in ApprovalService so it can't be bypassed.
        $this->requirePermission(KickBack::PERMISSION_VERIFY_PAYOUTS);

        return true;
    }

    public function actionIndex(string $tab = 'mine'): Response
    {
        $currentUserId = (int)Craft::$app->getUser()->getIdentity()->id;

        $query = ApprovalRecord::find()
            ->where(['status' => ApprovalRecord::STATUS_PENDING])
            ->orderBy(['dateCreated' => SORT_ASC]);

        if ($tab === 'mine') {
            $query->andWhere([
                'or',
                ['requestedUserId' => $currentUserId],
                ['requestedUserId' => null],
            ]);
        }

        /** @var ApprovalRecord[] $approvals */
        $approvals = $query->all();

        // Self-created targets filtered in PHP because the handler lookup is required.
        $rows = [];
        foreach ($approvals as $approval) {
            try {
                $handler = KickBack::getInstance()->approvals->getTargetHandler($approval->targetType);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if (!$handler->exists($approval->targetId)) {
                continue;
            }

            $creatorId = $handler->getCreatorUserId($approval->targetId);
            if ($creatorId === $currentUserId) {
                continue;
            }

            $rows[] = [
                'approval' => $approval,
                'label' => $handler->getRowLabel($approval->targetId),
                'url' => $handler->getRowUrl($approval->targetId),
                'creatorId' => $creatorId,
                'details' => $handler->getRowDetails($approval->targetId),
                'requestedUserName' => null,
            ];
        }

        $verifierIds = [];
        foreach ($rows as $row) {
            $id = $row['approval']->requestedUserId;
            if ($id !== null) {
                $verifierIds[$id] = true;
            }
        }
        $verifiers = [];
        if ($verifierIds !== []) {
            /** @var User[] $users */
            $users = User::find()->id(array_keys($verifierIds))->all();
            foreach ($users as $user) {
                $verifiers[$user->id] = $user->friendlyName;
            }
        }
        foreach ($rows as &$row) {
            $id = $row['approval']->requestedUserId;
            $row['requestedUserName'] = $id !== null ? ($verifiers[$id] ?? null) : null;
        }
        unset($row);

        return $this->renderTemplate('kickback/approvals/index', [
            'rows' => $rows,
            'tab' => $tab,
        ]);
    }

    public function actionApprove(): Response
    {
        return $this->handleResolutionAction(
            'approve',
            Craft::t('kickback', 'Payout approved.'),
            false,
        );
    }

    public function actionReject(): Response
    {
        return $this->handleResolutionAction(
            'reject',
            Craft::t('kickback', 'Payout rejected.'),
            true,
        );
    }

    private function handleResolutionAction(string $method, string $successMessage, bool $requireRejectionNote): Response
    {
        $this->requirePostRequest();

        $approvalId = (int)Craft::$app->getRequest()->getRequiredBodyParam('approvalId');
        $note = Craft::$app->getRequest()->getBodyParam('note');
        $resolverId = (int)Craft::$app->getUser()->getIdentity()->id;

        try {
            KickBack::getInstance()->approvals->$method($approvalId, $resolverId, $note);
            Craft::$app->getSession()->setNotice($successMessage);
        } catch (ApprovalNotFoundException) {
            throw new NotFoundHttpException(Craft::t('kickback', 'Approval not found.'));
        } catch (ApprovalAlreadyResolvedException) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'This approval has already been resolved.'));
        } catch (ApprovalTargetMissingException) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'The payout no longer exists.'));
        } catch (SelfVerificationException $e) {
            Craft::$app->getSession()->setError($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            if ($requireRejectionNote) {
                // Empty/missing note - re-render the form with a translated error.
                throw new BadRequestHttpException(Craft::t('kickback', 'A rejection note is required.'));
            }
            throw $e;
        }

        return $this->redirectToPostedUrl();
    }
}
