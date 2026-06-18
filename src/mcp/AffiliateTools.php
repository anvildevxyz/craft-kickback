<?php

namespace anvildev\craftkickback\mcp;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\mcp\support\Presenter;
use Craft;
use Mcp\Capability\Attribute\McpTool;
use stimmt\craft\Mcp\attributes\McpToolMeta;
use stimmt\craft\Mcp\enums\ToolCategory;

/**
 * MCP tools for reading and managing Kickback affiliates.
 *
 * Reads are always available and redact payment PII. Writes (register/update)
 * go behind {@see ToolResponseTrait::guardWrite()} and cover profile/config
 * fields only — affiliate status transitions (approve/reject/suspend) and
 * balance/earning mutations are deliberately NOT exposed over MCP.
 */
class AffiliateTools
{
    use ToolResponseTrait;

    /**
     * List affiliates, optionally filtered by program, status or group.
     *
     * @param int|null $programId Filter by program.
     * @param string|null $status One of: active, pending, suspended, rejected.
     * @param int|null $groupId Filter by affiliate group.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_list_affiliates',
        description: 'List Kickback affiliates, optionally filtered by program, status and group. '
            . 'Payment details (PayPal/Stripe) are redacted.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function listAffiliates(
        ?int $programId = null,
        ?string $status = null,
        ?int $groupId = null,
        int $limit = 50,
        int $offset = 0,
    ): array {
        return $this->guard(function() use ($programId, $status, $groupId, $limit, $offset): array {
            $query = AffiliateElement::find()
                ->siteId('*')
                ->status(null)
                ->limit($this->clampLimit($limit))
                ->offset($this->clampOffset($offset));

            if ($programId !== null) {
                $query->programId($programId);
            }
            if ($status !== null) {
                $query->affiliateStatus($status);
            }
            if ($groupId !== null) {
                $query->groupId($groupId);
            }

            $affiliates = $query->all();

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
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_get_affiliate',
        description: 'Get a single Kickback affiliate by id, including balances and lifetime stats. '
            . 'Payment details are redacted.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function getAffiliate(int $id): array
    {
        return $this->guard(function() use ($id): array {
            $affiliate = KickBack::getInstance()->affiliates->getAffiliateById($id);
            if (!$affiliate instanceof AffiliateElement) {
                return ['error' => "Affiliate #{$id} not found."];
            }

            return ['affiliate' => Presenter::affiliate($affiliate)];
        });
    }

    /**
     * @param string $referralCode The affiliate's unique referral code.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_get_affiliate_by_code',
        description: 'Look up a Kickback affiliate by their unique referral code.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function getAffiliateByCode(string $referralCode): array
    {
        return $this->guard(function() use ($referralCode): array {
            $affiliate = KickBack::getInstance()->affiliates->getAffiliateByReferralCode($referralCode);
            if (!$affiliate instanceof AffiliateElement) {
                return ['error' => "No affiliate found for referral code '{$referralCode}'."];
            }

            return ['affiliate' => Presenter::affiliate($affiliate)];
        });
    }

    /**
     * Register a new affiliate for an existing Craft user.
     *
     * @param int $userId Craft user to enroll as an affiliate.
     * @param int $programId Program to enroll them in.
     * @param string|null $referralCode Custom referral code (auto-generated if omitted).
     * @param int|null $parentAffiliateId Parent affiliate, for MLM/multi-tier programs.
     * @param int|null $groupId Affiliate group to assign.
     * @param string|null $paypalEmail PayPal payout email.
     * @param string|null $payoutMethod Payout method handle.
     * @param string|null $notes Internal notes.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_register_affiliate',
        description: 'Enroll an existing Craft user as a Kickback affiliate. Honors the auto-approve setting; '
            . 'otherwise the affiliate starts pending (use the Control Panel to approve).',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN, dangerous: true)]
    public function registerAffiliate(
        int $userId,
        int $programId,
        ?string $referralCode = null,
        ?int $parentAffiliateId = null,
        ?int $groupId = null,
        ?string $paypalEmail = null,
        ?string $payoutMethod = null,
        ?string $notes = null,
    ): array {
        return $this->guardWrite(KickBack::PERMISSION_MANAGE_AFFILIATES, function() use (
            $userId,
            $programId,
            $referralCode,
            $parentAffiliateId,
            $groupId,
            $paypalEmail,
            $payoutMethod,
            $notes,
        ): array {
            $user = Craft::$app->getUsers()->getUserById($userId);
            if ($user === null) {
                return ['error' => "Craft user #{$userId} not found."];
            }
            if (KickBack::getInstance()->affiliates->getAffiliateByUserId($userId) !== null) {
                return ['error' => "User #{$userId} is already an affiliate."];
            }

            $attributes = array_filter([
                'referralCode' => $referralCode,
                'parentAffiliateId' => $parentAffiliateId,
                'groupId' => $groupId,
                'paypalEmail' => $paypalEmail,
                'payoutMethod' => $payoutMethod,
                'notes' => $notes,
            ], static fn($v) => $v !== null);

            $affiliate = KickBack::getInstance()->affiliates->registerAffiliate($user, $programId, $attributes);
            if (!$affiliate instanceof AffiliateElement) {
                return ['error' => 'Failed to register affiliate; see the Kickback logs for details.'];
            }

            return ['success' => true, 'affiliate' => Presenter::affiliate($affiliate)];
        });
    }

    /**
     * Update an affiliate's profile/config fields. Only provided (non-null)
     * fields change. Status, balances and lifetime stats are NOT editable here —
     * approvals and payouts run through the Control Panel.
     *
     * @param int $id Affiliate to update.
     * @param int|null $groupId Reassign to this affiliate group.
     * @param int|null $parentAffiliateId Reassign MLM parent.
     * @param float|null $commissionRateOverride Per-affiliate commission rate override.
     * @param string|null $commissionTypeOverride Override type: percentage or flat.
     * @param string|null $payoutMethod Payout method handle.
     * @param float|null $payoutThreshold Minimum balance before payout.
     * @param string|null $paypalEmail PayPal payout email.
     * @param string|null $notes Internal notes.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_update_affiliate',
        description: 'Update a Kickback affiliate\'s profile/config (group, MLM parent, commission override, '
            . 'payout method/threshold, PayPal email, notes). Cannot change status or balances.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN, dangerous: true)]
    public function updateAffiliate(
        int $id,
        ?int $groupId = null,
        ?int $parentAffiliateId = null,
        ?float $commissionRateOverride = null,
        ?string $commissionTypeOverride = null,
        ?string $payoutMethod = null,
        ?float $payoutThreshold = null,
        ?string $paypalEmail = null,
        ?string $notes = null,
    ): array {
        return $this->guardWrite(KickBack::PERMISSION_MANAGE_AFFILIATES, function() use (
            $id,
            $groupId,
            $parentAffiliateId,
            $commissionRateOverride,
            $commissionTypeOverride,
            $payoutMethod,
            $payoutThreshold,
            $paypalEmail,
            $notes,
        ): array {
            $affiliate = KickBack::getInstance()->affiliates->getAffiliateById($id);
            if (!$affiliate instanceof AffiliateElement) {
                return ['error' => "Affiliate #{$id} not found."];
            }

            $fields = array_filter([
                'groupId' => $groupId,
                'parentAffiliateId' => $parentAffiliateId,
                'commissionRateOverride' => $commissionRateOverride,
                'commissionTypeOverride' => $commissionTypeOverride,
                'payoutMethod' => $payoutMethod,
                'payoutThreshold' => $payoutThreshold,
                'paypalEmail' => $paypalEmail,
                'notes' => $notes,
            ], static fn($v) => $v !== null);

            if ($fields === []) {
                return ['error' => 'Provide at least one field to update.'];
            }

            foreach ($fields as $field => $value) {
                $affiliate->$field = $value;
            }

            if (!Craft::$app->getElements()->saveElement($affiliate)) {
                return ['error' => 'Failed to update affiliate.', 'validationErrors' => $affiliate->getErrors()];
            }

            return ['success' => true, 'affiliate' => Presenter::affiliate($affiliate)];
        });
    }
}
