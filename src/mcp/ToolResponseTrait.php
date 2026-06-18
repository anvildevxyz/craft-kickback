<?php

namespace anvildev\craftkickback\mcp;

use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\mcp\support\Presenter;
use Craft;
use Throwable;

/**
 * Shared error handling and authorization for Kickback's MCP tools.
 *
 * Reads go through {@see self::guard()}; anything that creates or updates goes
 * through {@see self::guardWrite()}, which is default-deny. The trait references
 * nothing from the craft-mcp package, so a tool class stays usable (and
 * unit-testable) even when that plugin is not installed.
 */
trait ToolResponseTrait
{
    /**
     * Run a read tool body, translating exceptions into an error response.
     *
     * @param \Closure(): array<string, mixed> $fn
     * @return array<string, mixed>
     */
    private function guard(\Closure $fn): array
    {
        try {
            /** @var array<string, mixed> $result */
            $result = Presenter::jsonSafe($fn());
            return $result;
        } catch (Throwable $e) {
            Craft::warning('Kickback MCP tool failed: ' . $e->getMessage(), __METHOD__);

            // Only Kickback's own typed exceptions carry client-safe messages.
            // Everything else (PDO/Yii/driver/internal) may embed SQL, schema or
            // paths, so it is reduced to a generic message — details stay in logs.
            $isOwnException = str_starts_with($e::class, 'anvildev\\craftkickback\\exceptions\\');

            return [
                'error' => $isOwnException
                    ? $e->getMessage()
                    : 'An internal error occurred while running the tool; see the Kickback/Craft logs for details.',
                'type' => (new \ReflectionClass($e))->getShortName(),
            ];
        }
    }

    /**
     * Run a *mutating* tool body behind Kickback's MCP write authorization.
     *
     * Writes are refused unless an administrator has enabled
     * {@see \anvildev\craftkickback\models\Settings::$mcpWriteEnabled} (off by
     * default). When an authenticated Control-Panel user is present (web-context
     * MCP), the relevant `kickback-manage*` permission is also required — the
     * same gate the CP enforces. Money-moving operations are never exposed at all.
     *
     * @param \Closure(): array<string, mixed> $fn
     * @return array<string, mixed>
     */
    private function guardWrite(string $permission, \Closure $fn): array
    {
        $denied = $this->authorizeWrite($permission);
        if ($denied !== null) {
            return $denied;
        }

        return $this->guard($fn);
    }

    /**
     * @return array{error: string}|null
     */
    private function authorizeWrite(string $permission): ?array
    {
        if (!KickBack::getInstance()->getSettings()->mcpWriteEnabled) {
            return ['error' =>
                'Kickback MCP write operations are disabled. An administrator must enable '
                . '"Allow MCP write operations" in Kickback\'s settings before this tool can be used.',
            ];
        }

        // Defense in depth: in a web context an authenticated user is present, so
        // require the same permission the Control Panel does. In console/stdio
        // context there is no user — the setting above is the control there.
        $user = Craft::$app instanceof \craft\web\Application
            ? Craft::$app->getUser()->getIdentity()
            : null;
        if ($user !== null && !$user->admin && !$user->can($permission)) {
            return ['error' => "Not authorized: the {$permission} permission is required for this operation."];
        }

        return null;
    }

    /**
     * Hard ceiling for list/pagination tools, so a single MCP call can never
     * materialise an unbounded result set (memory exhaustion + bulk PII export).
     */
    private const LIST_LIMIT_MAX = 200;
    private const LIST_LIMIT_DEFAULT = 50;

    private function clampLimit(int $limit, int $max = self::LIST_LIMIT_MAX): int
    {
        if ($limit < 1) {
            return self::LIST_LIMIT_DEFAULT;
        }

        return min($limit, $max);
    }

    private function clampOffset(int $offset): int
    {
        return max(0, $offset);
    }
}
