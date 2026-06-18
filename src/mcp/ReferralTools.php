<?php

namespace anvildev\craftkickback\mcp;

use anvildev\craftkickback\elements\ReferralElement;
use anvildev\craftkickback\mcp\support\Presenter;
use Mcp\Capability\Attribute\McpTool;
use stimmt\craft\Mcp\attributes\McpToolMeta;
use stimmt\craft\Mcp\enums\ToolCategory;

/**
 * MCP tools for reading Kickback referrals (a tracked order attributed to an
 * affiliate). Read-only; customer email/id and tracking sub-id are redacted.
 */
class ReferralTools
{
    use ToolResponseTrait;

    /**
     * @param int|null $affiliateId Filter by affiliate.
     * @param int|null $programId Filter by program.
     * @param string|null $status One of: pending, approved, rejected, paid, flagged.
     * @param int|null $orderId Filter by Commerce order.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_list_referrals',
        description: 'List Kickback referrals, optionally filtered by affiliate, program, status and order. '
            . 'Customer details are redacted.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function listReferrals(
        ?int $affiliateId = null,
        ?int $programId = null,
        ?string $status = null,
        ?int $orderId = null,
        int $limit = 50,
        int $offset = 0,
    ): array {
        return $this->guard(function() use ($affiliateId, $programId, $status, $orderId, $limit, $offset): array {
            $query = ReferralElement::find()
                ->siteId('*')
                ->status(null)
                ->limit($this->clampLimit($limit))
                ->offset($this->clampOffset($offset));
            if ($affiliateId !== null) {
                $query->affiliateId($affiliateId);
            }
            if ($programId !== null) {
                $query->programId($programId);
            }
            if ($status !== null) {
                $query->referralStatus($status);
            }
            if ($orderId !== null) {
                $query->orderId($orderId);
            }
            $referrals = $query->all();

            return [
                'count' => count($referrals),
                'referrals' => array_map(static fn(ReferralElement $r) => Presenter::referral($r), $referrals),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_get_referral',
        description: 'Get a single Kickback referral by id. Customer details are redacted.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function getReferral(int $id): array
    {
        return $this->guard(function() use ($id): array {
            $referral = ReferralElement::find()->siteId('*')->status(null)->id($id)->one();
            if (!$referral instanceof ReferralElement) {
                return ['error' => "Referral #{$id} not found."];
            }

            return ['referral' => Presenter::referral($referral)];
        });
    }
}
