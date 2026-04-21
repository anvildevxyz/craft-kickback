<?php

declare(strict_types=1);

namespace anvildev\craftkickback\exceptions;

class ApprovalTargetMissingException extends \RuntimeException
{
    public static function forTarget(string $targetType, int $targetId): self
    {
        return new self("Approval target missing: {$targetType}#{$targetId}");
    }
}
