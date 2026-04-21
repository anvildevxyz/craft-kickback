<?php

declare(strict_types=1);

namespace anvildev\craftkickback\exceptions;

class ApprovalNotFoundException extends \RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("Approval not found: #{$id}");
    }
}
