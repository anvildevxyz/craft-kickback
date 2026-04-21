<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\CommissionRuleElement;
use craft\base\Component;
use craft\helpers\Json;

/**
 * Manages commission rules for products, categories, and MLM tiers.
 */
class CommissionRuleService extends Component
{
    public function getRuleById(int $id): ?CommissionRuleElement
    {
        return CommissionRuleElement::find()->id($id)->one();
    }

    /**
     * @return CommissionRuleElement[]
     */
    public function getRulesByProgramId(int $programId): array
    {
        return CommissionRuleElement::find()
            ->programId($programId)
            ->orderBy(['kickback_commission_rules.priority' => SORT_DESC])
            ->all();
    }

    /**
     * @return CommissionRuleElement[]
     */
    public function getRulesByType(int $programId, string $type): array
    {
        return CommissionRuleElement::find()
            ->programId($programId)
            ->type($type)
            ->orderBy(['kickback_commission_rules.priority' => SORT_DESC])
            ->all();
    }

    public function findProductRule(int $programId, int $productId): ?CommissionRuleElement
    {
        return $this->findTargetedRule($programId, CommissionRuleElement::TYPE_PRODUCT, $productId);
    }

    public function findCategoryRule(int $programId, int $categoryId): ?CommissionRuleElement
    {
        return $this->findTargetedRule($programId, CommissionRuleElement::TYPE_CATEGORY, $categoryId);
    }

    private function findTargetedRule(int $programId, string $type, int $targetId): ?CommissionRuleElement
    {
        return CommissionRuleElement::find()
            ->programId($programId)
            ->type($type)
            ->targetId($targetId)
            ->one();
    }

    public function findMlmTierRule(int $programId, int $tierLevel): ?CommissionRuleElement
    {
        return CommissionRuleElement::find()
            ->programId($programId)
            ->type(CommissionRuleElement::TYPE_MLM_TIER)
            ->tierLevel($tierLevel)
            ->one();
    }

    public function findTieredRule(int $programId, int $affiliateId): ?CommissionRuleElement
    {
        $rules = CommissionRuleElement::find()
            ->programId($programId)
            ->type(CommissionRuleElement::TYPE_TIERED)
            ->orderBy(['kickback_commission_rules.tierThreshold' => SORT_DESC, 'kickback_commission_rules.priority' => SORT_DESC])
            ->all();

        foreach ($rules as $rule) {
            if ($this->countAffiliateReferrals($affiliateId, $rule->lookbackDays) >= ($rule->tierThreshold ?? 0)) {
                return $rule;
            }
        }
        return null;
    }

    public function findBonusRule(int $programId): ?CommissionRuleElement
    {
        $now = new \DateTime();

        foreach (CommissionRuleElement::find()->programId($programId)->type(CommissionRuleElement::TYPE_BONUS)->orderBy(['kickback_commission_rules.priority' => SORT_DESC])->all() as $rule) {
            $cond = $rule->conditions !== null ? Json::decodeIfJson($rule->conditions) : null;
            if (!is_array($cond)) {
                return $rule;
            }
            if (isset($cond['startDate']) && $now < new \DateTime($cond['startDate'])) {
                continue;
            }
            if (isset($cond['endDate']) && $now > new \DateTime($cond['endDate'])) {
                continue;
            }
            return $rule;
        }
        return null;
    }

    private function countAffiliateReferrals(int $affiliateId, ?int $lookbackDays): int
    {
        $query = \anvildev\craftkickback\records\ReferralRecord::find()
            ->where(['affiliateId' => $affiliateId])
            ->andWhere(['not', ['status' => \anvildev\craftkickback\models\Referral::STATUS_REJECTED]]);

        if ($lookbackDays !== null) {
            $query->andWhere(['>=', 'dateCreated', (new \DateTime())->modify("-{$lookbackDays} days")->format('Y-m-d H:i:s')]);
        }
        return (int)$query->count();
    }
}
