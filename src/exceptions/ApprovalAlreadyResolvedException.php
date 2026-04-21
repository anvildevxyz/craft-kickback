<?php

declare(strict_types=1);

namespace anvildev\craftkickback\exceptions;

class ApprovalAlreadyResolvedException extends \RuntimeException
{
    public static function forApproval(int $id, string $currentStatus): self
    {
        return new self("Approval #{$id} is already resolved (status: {$currentStatus}).");
    }
}
