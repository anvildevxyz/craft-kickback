<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Mcp;

use anvildev\craftkickback\mcp\AffiliateGroupTools;
use anvildev\craftkickback\mcp\AffiliateTools;
use anvildev\craftkickback\mcp\CommissionRuleTools;
use anvildev\craftkickback\mcp\CommissionTools;
use anvildev\craftkickback\mcp\PayoutTools;
use anvildev\craftkickback\mcp\ProgramTools;
use anvildev\craftkickback\mcp\ReferralTools;
use anvildev\craftkickback\mcp\ReportTools;
use anvildev\craftkickback\mcp\support\Presenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Verifies Kickback's craft-mcp integration: each tool class declares the
 * expected `#[McpTool]` set, every mutating tool runs behind the default-deny
 * write gate, money-moving operations are never exposed, customer PII is
 * redacted, and list tools clamp their page size.
 *
 * Assertions read attribute arguments reflectively (never instantiating the
 * attribute classes) or scan source, so the suite passes whether or not the
 * optional stimmt/craft-mcp package is installed and without a Craft bootstrap.
 */
class McpToolsTest extends TestCase
{
    private const MCP_TOOL_ATTR = 'Mcp\\Capability\\Attribute\\McpTool';

    /** @var array<class-string, list<string>> */
    private const EXPECTED_TOOLS = [
        AffiliateTools::class => [
            'kickback_list_affiliates', 'kickback_get_affiliate', 'kickback_get_affiliate_by_code',
            'kickback_register_affiliate', 'kickback_update_affiliate',
        ],
        AffiliateGroupTools::class => [
            'kickback_list_affiliate_groups', 'kickback_get_affiliate_group',
        ],
        ProgramTools::class => [
            'kickback_list_programs', 'kickback_get_program', 'kickback_create_program', 'kickback_update_program',
        ],
        CommissionRuleTools::class => [
            'kickback_list_commission_rules', 'kickback_get_commission_rule',
            'kickback_create_commission_rule', 'kickback_update_commission_rule',
        ],
        ReferralTools::class => ['kickback_list_referrals', 'kickback_get_referral'],
        CommissionTools::class => ['kickback_list_commissions', 'kickback_get_commission'],
        PayoutTools::class => ['kickback_list_payouts', 'kickback_get_payout'],
        ReportTools::class => [
            'kickback_stats', 'kickback_top_affiliates', 'kickback_daily_commissions', 'kickback_daily_referrals',
        ],
    ];

    /**
     * @param class-string $class
     * @param list<string> $expected
     */
    #[DataProvider('toolClassProvider')]
    public function testToolClassDeclaresExpectedTools(string $class, array $expected): void
    {
        $declared = $this->declaredToolNames($class);
        sort($declared);
        $want = $expected;
        sort($want);
        $this->assertSame($want, $declared, "$class should declare exactly its expected MCP tools.");
    }

    public function testTotalToolCountIs25(): void
    {
        $total = array_sum(array_map(fn($n) => count($n), self::EXPECTED_TOOLS));
        $this->assertSame(25, $total);

        $declared = 0;
        foreach (array_keys(self::EXPECTED_TOOLS) as $class) {
            $declared += count($this->declaredToolNames($class));
        }
        $this->assertSame(25, $declared, 'All 8 tool classes together must declare 25 tools.');
    }

    public function testRedactEmailMasksLocalPart(): void
    {
        $this->assertSame('ja***@example.com', Presenter::redactEmail('jane@example.com'));
        $this->assertSame('a***@x.io', Presenter::redactEmail('a@x.io'));
        $this->assertSame('***', Presenter::redactEmail('notanemail'));
        $this->assertNull(Presenter::redactEmail(null));
        $this->assertSame('', Presenter::redactEmail(''));
    }

    public function testRedactAccountKeepsLastFour(): void
    {
        $this->assertSame('********2233', Presenter::redactAccount('+41791112233'));
        $this->assertSame('***', Presenter::redactAccount('abcd'));
        $this->assertNull(Presenter::redactAccount(null));
        // Never leaks more than the last 4 chars.
        $masked = Presenter::redactAccount('acct_1234567890');
        $this->assertSame('7890', substr((string)$masked, -4));
        $this->assertStringStartsWith('***', (string)$masked);
    }

    public function testJsonSafeGuardsRecursionDepth(): void
    {
        // A value nested past the depth cap collapses to null instead of recursing.
        $deep = 'leaf';
        for ($i = 0; $i < 25; $i++) {
            $deep = [$deep];
        }
        $safe = Presenter::jsonSafe($deep);
        $this->assertIsArray($safe);
    }

    public function testJsonSafeFormatsDatesAndNonFiniteFloats(): void
    {
        $out = Presenter::jsonSafe([
            'when' => new \DateTimeImmutable('2026-06-17T10:00:00+00:00'),
            'inf' => INF,
            'n' => 5,
        ]);
        $this->assertSame('2026-06-17T10:00:00+00:00', $out['when']);
        $this->assertNull($out['inf']);
        $this->assertSame(5, $out['n']);
    }

    public function testJsonSafeStubsUnknownObjects(): void
    {
        $obj = new class() {
            public string $secret = 'do-not-dump';
        };
        $this->assertSame(['_class' => $obj::class], Presenter::jsonSafe($obj));
    }

    public function testMutatingToolsRouteThroughWriteGate(): void
    {
        // Every tool flagged dangerous:true must use guardWrite(); read tools must not.
        foreach ($this->mcpSourceFiles() as $file) {
            $src = file_get_contents($file);
            $this->assertSame(
                substr_count($src, 'dangerous: true'),
                substr_count($src, '$this->guardWrite('),
                basename($file) . ': dangerous tools must use guardWrite() and read tools must not.',
            );
        }
    }

    public function testWriteGateIsDefaultDeny(): void
    {
        $src = file_get_contents($this->srcDir() . '/mcp/ToolResponseTrait.php');
        $this->assertStringContainsString('function guardWrite(string $permission', $src);
        $this->assertStringContainsString('mcpWriteEnabled', $src, 'Writes must be gated by the default-off setting.');
        $this->assertStringContainsString('$user->can($permission)', $src, 'Web-context permission check required.');
    }

    public function testSettingsHasMcpWriteEnabledDefaultFalse(): void
    {
        $src = file_get_contents($this->srcDir() . '/models/Settings.php');
        $this->assertMatchesRegularExpression('/public bool \$mcpWriteEnabled = false;/', $src);
        $this->assertStringContainsString("[['mcpWriteEnabled'], 'boolean']", $src);
    }

    public function testPresenterRedactsPiiByDefault(): void
    {
        $src = file_get_contents($this->srcDir() . '/mcp/support/Presenter.php');
        $this->assertStringContainsString('AffiliateElement $a, bool $redactPii = true', $src);
        $this->assertStringContainsString('ReferralElement $r, bool $redactPii = true', $src);
        $this->assertStringContainsString('PayoutElement $p, bool $redactPii = true', $src);
    }

    public function testListToolsClampPageSize(): void
    {
        $trait = file_get_contents($this->srcDir() . '/mcp/ToolResponseTrait.php');
        $this->assertStringContainsString('LIST_LIMIT_MAX', $trait);
        $this->assertStringContainsString('function clampLimit(', $trait);

        foreach ($this->mcpSourceFiles() as $file) {
            $src = file_get_contents($file);
            if (str_contains($src, 'list')) {
                $this->assertStringContainsString(
                    'clampLimit(',
                    $src,
                    basename($file) . ': list tools must clamp the page size.',
                );
            }
        }
    }

    public function testNoMoneyMovingMethodsExposed(): void
    {
        // The MCP surface must never call approval or money-moving service methods.
        $forbidden = [
            'approveAffiliate', 'rejectAffiliate', 'suspendAffiliate', 'reactivateAffiliate', 'recordPayout',
            'approveReferral', 'rejectReferral', 'approveCommission', 'rejectCommission', 'reverseCommission',
            'createPayout', 'createBatchPayouts', 'completePayout', 'processPayout', 'failPayout', 'cancelPayout',
            'markReversed', 'addPendingBalance', 'deductPendingBalance',
        ];
        $all = '';
        foreach ($this->mcpSourceFiles() as $file) {
            $all .= file_get_contents($file);
        }
        foreach ($forbidden as $method) {
            $this->assertStringNotContainsString(
                "->{$method}(",
                $all,
                "Money-moving/approval method {$method}() must not be exposed over MCP.",
            );
        }
    }

    /**
     * @return array<string, array{class-string, list<string>}>
     */
    public static function toolClassProvider(): array
    {
        $cases = [];
        foreach (self::EXPECTED_TOOLS as $class => $names) {
            $cases[$class] = [$class, $names];
        }
        return $cases;
    }

    /**
     * @param class-string $class
     * @return list<string>
     */
    private function declaredToolNames(string $class): array
    {
        $names = [];
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attr = $method->getAttributes(self::MCP_TOOL_ATTR)[0] ?? null;
            $name = $attr?->getArguments()['name'] ?? null;
            if ($name !== null) {
                $names[] = $name;
            }
        }
        return $names;
    }

    private function srcDir(): string
    {
        return dirname(__DIR__, 3) . '/src';
    }

    /**
     * @return list<string>
     */
    private function mcpSourceFiles(): array
    {
        return array_values(array_filter(
            glob($this->srcDir() . '/mcp/*.php') ?: [],
            static fn($f) => basename($f) !== 'ToolResponseTrait.php',
        ));
    }
}
