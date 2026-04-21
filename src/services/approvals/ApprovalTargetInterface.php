<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services\approvals;

/**
 * Contract implemented by anything that can be the target of an approval.
 * Implementations must be stateless and constructable without arguments.
 */
interface ApprovalTargetInterface
{
    /**
     * User id who created the target, or null for automated sources (console/cron).
     */
    public function getCreatorUserId(int $targetId): ?int;

    /**
     * Called from ApprovalService::reject() inside the same transaction, after
     * the approval row has been flipped to 'rejected'. Propagates the rejection
     * into the target's own state. Must not touch balances or commissions.
     */
    public function onReject(int $targetId): void;

    /**
     * Short human label shown in the verification queue.
     */
    public function getRowLabel(int $targetId): string;

    /**
     * CP URL the verification queue links to for this target.
     */
    public function getRowUrl(int $targetId): string;

    public function exists(int $targetId): bool;

    /**
     * Display-ready detail fields for the verification queue table. Known
     * keys: amount, method, affiliate, createdBy. Values must be plain text.
     *
     * @return array<string, string|null>
     */
    public function getRowDetails(int $targetId): array;
}
