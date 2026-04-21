<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\services\NotificationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NotificationServiceEmailTest extends TestCase
{
    #[Test]
    public function normalizeEmailAcceptsValidAddress(): void
    {
        self::assertSame(
            'alerts@example.com',
            NotificationService::normalizeEmail('alerts@example.com'),
        );
    }

    #[Test]
    public function normalizeEmailRejectsUnresolvedEnvPlaceholder(): void
    {
        self::assertNull(NotificationService::normalizeEmail('$SYSTEM_EMAIL'));
    }

    #[Test]
    public function normalizeEmailRejectsMalformedAddress(): void
    {
        self::assertNull(NotificationService::normalizeEmail('not-an-email'));
    }
}

