<?php

namespace anvildev\craftkickback\mcp;

use anvildev\craftkickback\elements\CommissionRuleElement;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\mcp\support\Presenter;
use Craft;
use Mcp\Capability\Attribute\McpTool;
use stimmt\craft\Mcp\attributes\McpToolMeta;
use stimmt\craft\Mcp\enums\ToolCategory;

/**
 * MCP tools for Kickback commission rules (per-program rate rules: product,
 * category, tiered, bonus, mlm_tier).
 */
class CommissionRuleTools
{
    use ToolResponseTrait;

    /**
     * @param int|null $programId Filter by program.
     * @param string|null $type One of: product, category, tiered, bonus, mlm_tier.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_list_commission_rules',
        description: 'List Kickback commission rules, optionally filtered by program and type, in priority order.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function listCommissionRules(?int $programId = null, ?string $type = null, int $limit = 50, int $offset = 0): array
    {
        return $this->guard(function() use ($programId, $type, $limit, $offset): array {
            $query = CommissionRuleElement::find()
                ->siteId('*')
                ->status(null)
                ->orderBy(['kickback_commission_rules.priority' => SORT_DESC])
                ->limit($this->clampLimit($limit))
                ->offset($this->clampOffset($offset));
            if ($programId !== null) {
                $query->programId($programId);
            }
            if ($type !== null) {
                $query->type($type);
            }
            $rules = $query->all();

            return [
                'count' => count($rules),
                'rules' => array_map(static fn(CommissionRuleElement $r) => Presenter::commissionRule($r), $rules),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_get_commission_rule',
        description: 'Get a single Kickback commission rule by id.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function getCommissionRule(int $id): array
    {
        return $this->guard(function() use ($id): array {
            $rule = CommissionRuleElement::find()->siteId('*')->status(null)->id($id)->one();
            if (!$rule instanceof CommissionRuleElement) {
                return ['error' => "Commission rule #{$id} not found."];
            }

            return ['rule' => Presenter::commissionRule($rule)];
        });
    }

    /**
     * Create a commission rule.
     *
     * @param int $programId Program the rule belongs to.
     * @param string $name Rule name.
     * @param string $type One of: product, category, tiered, bonus, mlm_tier.
     * @param float $commissionRate Commission rate for the rule.
     * @param string|null $commissionType One of: percentage, flat.
     * @param int|null $targetId Product/category id for product/category rules.
     * @param int|null $tierThreshold Referral-count threshold for tiered rules.
     * @param int|null $tierLevel MLM tier level for mlm_tier rules.
     * @param int|null $lookbackDays Lookback window for tiered rules.
     * @param int|null $priority Resolution priority (higher wins).
     * @param string|null $conditions JSON-encoded extra conditions.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_create_commission_rule',
        description: 'Create a Kickback commission rule for a program. Returns the created rule.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN, dangerous: true)]
    public function createCommissionRule(
        int $programId,
        string $name,
        string $type,
        float $commissionRate,
        ?string $commissionType = null,
        ?int $targetId = null,
        ?int $tierThreshold = null,
        ?int $tierLevel = null,
        ?int $lookbackDays = null,
        ?int $priority = null,
        ?string $conditions = null,
    ): array {
        return $this->guardWrite(KickBack::PERMISSION_MANAGE_COMMISSIONS, function() use (
            $programId,
            $name,
            $type,
            $commissionRate,
            $commissionType,
            $targetId,
            $tierThreshold,
            $tierLevel,
            $lookbackDays,
            $priority,
            $conditions,
        ): array {
            $rule = new CommissionRuleElement();
            $rule->programId = $programId;
            $rule->name = $name;
            $rule->type = $type;
            $rule->commissionRate = $commissionRate;
            foreach ([
                'commissionType' => $commissionType,
                'targetId' => $targetId,
                'tierThreshold' => $tierThreshold,
                'tierLevel' => $tierLevel,
                'lookbackDays' => $lookbackDays,
                'priority' => $priority,
                'conditions' => $conditions,
            ] as $field => $value) {
                if ($value !== null) {
                    $rule->$field = $value;
                }
            }

            if (!Craft::$app->getElements()->saveElement($rule)) {
                return ['error' => 'Failed to create commission rule.', 'validationErrors' => $rule->getErrors()];
            }

            return ['success' => true, 'rule' => Presenter::commissionRule($rule)];
        });
    }

    /**
     * Update a commission rule. Only provided (non-null) fields change.
     *
     * @param int $id Rule to update.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_update_commission_rule',
        description: 'Update a Kickback commission rule by id. Only the fields you pass are changed.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN, dangerous: true)]
    public function updateCommissionRule(
        int $id,
        ?string $name = null,
        ?string $type = null,
        ?float $commissionRate = null,
        ?string $commissionType = null,
        ?int $targetId = null,
        ?int $tierThreshold = null,
        ?int $tierLevel = null,
        ?int $lookbackDays = null,
        ?int $priority = null,
        ?string $conditions = null,
    ): array {
        return $this->guardWrite(KickBack::PERMISSION_MANAGE_COMMISSIONS, function() use (
            $id,
            $name,
            $type,
            $commissionRate,
            $commissionType,
            $targetId,
            $tierThreshold,
            $tierLevel,
            $lookbackDays,
            $priority,
            $conditions,
        ): array {
            $rule = CommissionRuleElement::find()->siteId('*')->status(null)->id($id)->one();
            if (!$rule instanceof CommissionRuleElement) {
                return ['error' => "Commission rule #{$id} not found."];
            }

            $fields = array_filter([
                'name' => $name,
                'type' => $type,
                'commissionRate' => $commissionRate,
                'commissionType' => $commissionType,
                'targetId' => $targetId,
                'tierThreshold' => $tierThreshold,
                'tierLevel' => $tierLevel,
                'lookbackDays' => $lookbackDays,
                'priority' => $priority,
                'conditions' => $conditions,
            ], static fn($v) => $v !== null);

            if ($fields === []) {
                return ['error' => 'Provide at least one field to update.'];
            }
            foreach ($fields as $field => $value) {
                $rule->$field = $value;
            }

            if (!Craft::$app->getElements()->saveElement($rule)) {
                return ['error' => 'Failed to update commission rule.', 'validationErrors' => $rule->getErrors()];
            }

            return ['success' => true, 'rule' => Presenter::commissionRule($rule)];
        });
    }
}
