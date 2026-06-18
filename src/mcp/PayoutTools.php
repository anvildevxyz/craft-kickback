<?php

namespace anvildev\craftkickback\mcp;

use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\mcp\support\Presenter;
use Mcp\Capability\Attribute\McpTool;
use stimmt\craft\Mcp\attributes\McpToolMeta;
use stimmt\craft\Mcp\enums\ToolCategory;

/**
 * MCP tools for reading Kickback payouts. Read-only — creating, processing,
 * completing and reversing payouts move real money and are never exposed over
 * MCP; manage them in the Control Panel. Gateway transaction/batch refs are
 * redacted.
 */
class PayoutTools
{
    use ToolResponseTrait;

    /**
     * @param int|null $affiliateId Filter by affiliate.
     * @param string|null $status One of: pending, processing, completed, failed, rejected, reversed.
     * @param string|null $method One of: paypal, stripe, manual.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_list_payouts',
        description: 'List Kickback payouts, optionally filtered by affiliate, status and method. '
            . 'Gateway references are redacted.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function listPayouts(
        ?int $affiliateId = null,
        ?string $status = null,
        ?string $method = null,
        int $limit = 50,
        int $offset = 0,
    ): array {
        return $this->guard(function() use ($affiliateId, $status, $method, $limit, $offset): array {
            $query = PayoutElement::find()
                ->siteId('*')
                ->status(null)
                ->limit($this->clampLimit($limit))
                ->offset($this->clampOffset($offset));
            if ($affiliateId !== null) {
                $query->affiliateId($affiliateId);
            }
            if ($status !== null) {
                $query->payoutStatus($status);
            }
            if ($method !== null) {
                $query->method($method);
            }
            $payouts = $query->all();

            return [
                'count' => count($payouts),
                'payouts' => array_map(static fn(PayoutElement $p) => Presenter::payout($p), $payouts),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_get_payout',
        description: 'Get a single Kickback payout by id. Gateway references are redacted.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function getPayout(int $id): array
    {
        return $this->guard(function() use ($id): array {
            $payout = PayoutElement::find()->siteId('*')->status(null)->id($id)->one();
            if (!$payout instanceof PayoutElement) {
                return ['error' => "Payout #{$id} not found."];
            }

            return ['payout' => Presenter::payout($payout)];
        });
    }
}
