<?php

namespace anvildev\craftkickback\mcp;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\mcp\support\Presenter;
use Mcp\Capability\Attribute\McpTool;
use stimmt\craft\Mcp\attributes\McpToolMeta;
use stimmt\craft\Mcp\enums\ToolCategory;

/**
 * MCP tools exposing Kickback's reporting surface. All read-only. Date arguments
 * are Y-m-d; omit them for an all-time view.
 */
class ReportTools
{
    use ToolResponseTrait;

    /**
     * @param string|null $startDate Window start, Y-m-d.
     * @param string|null $endDate Window end, Y-m-d.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_stats',
        description: 'Aggregate Kickback stats over a date range: affiliate/referral counts and commission/payout totals.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function stats(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->guard(static fn(): array => [
            'stats' => KickBack::getInstance()->reporting->getStats($startDate, $endDate),
        ]);
    }

    /**
     * @param int $limit How many affiliates to return (clamped).
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_top_affiliates',
        description: 'List the top Kickback affiliates by lifetime earnings. Payment details are redacted.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function topAffiliates(int $limit = 10): array
    {
        return $this->guard(function() use ($limit): array {
            $affiliates = KickBack::getInstance()->reporting->getTopAffiliates($this->clampLimit($limit));

            return [
                'count' => count($affiliates),
                'affiliates' => array_map(
                    static fn(AffiliateElement $a) => Presenter::affiliate($a),
                    $affiliates,
                ),
            ];
        });
    }

    /**
     * @param string|null $startDate Window start, Y-m-d.
     * @param string|null $endDate Window end, Y-m-d.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_daily_commissions',
        description: 'Daily commission totals over a date range (chart data: date + total).',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function dailyCommissions(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->guard(static fn(): array => [
            'daily' => KickBack::getInstance()->reporting->getDailyCommissions($startDate, $endDate),
        ]);
    }

    /**
     * @param string|null $startDate Window start, Y-m-d.
     * @param string|null $endDate Window end, Y-m-d.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_daily_referrals',
        description: 'Daily referral counts over a date range (chart data: date + total).',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function dailyReferrals(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->guard(static fn(): array => [
            'daily' => KickBack::getInstance()->reporting->getDailyReferrals($startDate, $endDate),
        ]);
    }
}
