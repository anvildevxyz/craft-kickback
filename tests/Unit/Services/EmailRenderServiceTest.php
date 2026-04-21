<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\services\EmailRenderService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailRenderServiceTest extends TestCase
{
    #[Test]
    public function resolveTemplatePathReturnsPluginDefaultWhenNoOverride(): void
    {
        $service = new EmailRenderService();

        $method = new \ReflectionMethod($service, 'resolveTemplatePath');

        $result = $method->invoke($service, 'approval', fn(string $path) => false);

        self::assertSame('kickback/emails/approval', $result);
    }

    #[Test]
    public function resolveTemplatePathReturnsUserOverrideWhenExists(): void
    {
        $service = new EmailRenderService();
        $method = new \ReflectionMethod($service, 'resolveTemplatePath');

        $result = $method->invoke(
            $service,
            'approval',
            fn(string $path) => $path === '_kickback/emails/approval',
        );

        self::assertSame('_kickback/emails/approval', $result);
    }
}
