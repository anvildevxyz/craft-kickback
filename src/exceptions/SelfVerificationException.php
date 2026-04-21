<?php

declare(strict_types=1);

namespace anvildev\craftkickback\exceptions;

class SelfVerificationException extends \DomainException
{
    public function __construct(string $message = 'You cannot verify a payout you created yourself.')
    {
        parent::__construct($message);
    }
}
