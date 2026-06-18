<?php

namespace anvildev\craftkickback\mcp;

use anvildev\craftkickback\elements\ProgramElement;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\mcp\support\Presenter;
use Mcp\Capability\Attribute\McpTool;
use stimmt\craft\Mcp\attributes\McpToolMeta;
use stimmt\craft\Mcp\enums\ToolCategory;

/**
 * MCP tools for Kickback programs. Programs are localized, so queries collapse
 * per-site duplicates with `->unique()` and search across all sites.
 */
class ProgramTools
{
    use ToolResponseTrait;

    /**
     * @param string|null $status One of: active, inactive, archived.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_list_programs',
        description: 'List Kickback affiliate programs (id, name, handle, default commission, cookie window, status).',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function listPrograms(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        return $this->guard(function() use ($status, $limit, $offset): array {
            $query = ProgramElement::find()
                ->siteId('*')
                ->status(null)
                ->unique()
                ->limit($this->clampLimit($limit))
                ->offset($this->clampOffset($offset));
            if ($status !== null) {
                $query->programStatus($status);
            }
            $programs = $query->all();

            return [
                'count' => count($programs),
                'programs' => array_map(static fn(ProgramElement $p) => Presenter::program($p), $programs),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_get_program',
        description: 'Get a single Kickback program by id.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function getProgram(int $id): array
    {
        return $this->guard(function() use ($id): array {
            $program = ProgramElement::find()->siteId('*')->status(null)->unique()->id($id)->one();
            if (!$program instanceof ProgramElement) {
                return ['error' => "Program #{$id} not found."];
            }

            return ['program' => Presenter::program($program)];
        });
    }

    /**
     * Create an affiliate program.
     *
     * @param string $name Program name (required).
     * @param string|null $handle Unique handle (auto-derived from name if omitted).
     * @param string|null $description Program description.
     * @param float|null $defaultCommissionRate Default commission rate.
     * @param string|null $defaultCommissionType One of: percentage, flat.
     * @param int|null $cookieDuration Attribution cookie lifetime in days.
     * @param bool|null $allowSelfReferral Whether affiliates may refer themselves.
     * @param bool|null $enableCouponCreation Whether coupon-based referrals are enabled.
     * @param string|null $programStatus One of: active, inactive, archived.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_create_program',
        description: 'Create a Kickback affiliate program. Returns the created program.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN, dangerous: true)]
    public function createProgram(
        string $name,
        ?string $handle = null,
        ?string $description = null,
        ?float $defaultCommissionRate = null,
        ?string $defaultCommissionType = null,
        ?int $cookieDuration = null,
        ?bool $allowSelfReferral = null,
        ?bool $enableCouponCreation = null,
        ?string $programStatus = null,
    ): array {
        return $this->guardWrite(KickBack::PERMISSION_MANAGE_PROGRAMS, function() use (
            $name,
            $handle,
            $description,
            $defaultCommissionRate,
            $defaultCommissionType,
            $cookieDuration,
            $allowSelfReferral,
            $enableCouponCreation,
            $programStatus,
        ): array {
            $program = new ProgramElement();
            $program->name = $name;
            $program->handle = $handle ?? \craft\helpers\StringHelper::toHandle($name);
            foreach ([
                'description' => $description,
                'defaultCommissionRate' => $defaultCommissionRate,
                'defaultCommissionType' => $defaultCommissionType,
                'cookieDuration' => $cookieDuration,
                'allowSelfReferral' => $allowSelfReferral,
                'enableCouponCreation' => $enableCouponCreation,
                'programStatus' => $programStatus,
            ] as $field => $value) {
                if ($value !== null) {
                    $program->$field = $value;
                }
            }

            if (!KickBack::getInstance()->programs->saveProgram($program)) {
                return ['error' => 'Failed to create program.', 'validationErrors' => $program->getErrors()];
            }

            return ['success' => true, 'program' => Presenter::program($program)];
        });
    }

    /**
     * Update a program. Only provided (non-null) fields change.
     *
     * @param int $id Program to update.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_update_program',
        description: 'Update a Kickback program by id. Only the fields you pass are changed. '
            . 'Set programStatus=archived to retire it.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN, dangerous: true)]
    public function updateProgram(
        int $id,
        ?string $name = null,
        ?string $description = null,
        ?float $defaultCommissionRate = null,
        ?string $defaultCommissionType = null,
        ?int $cookieDuration = null,
        ?bool $allowSelfReferral = null,
        ?bool $enableCouponCreation = null,
        ?string $programStatus = null,
    ): array {
        return $this->guardWrite(KickBack::PERMISSION_MANAGE_PROGRAMS, function() use (
            $id,
            $name,
            $description,
            $defaultCommissionRate,
            $defaultCommissionType,
            $cookieDuration,
            $allowSelfReferral,
            $enableCouponCreation,
            $programStatus,
        ): array {
            $program = ProgramElement::find()->siteId('*')->status(null)->unique()->id($id)->one();
            if (!$program instanceof ProgramElement) {
                return ['error' => "Program #{$id} not found."];
            }

            $fields = array_filter([
                'name' => $name,
                'description' => $description,
                'defaultCommissionRate' => $defaultCommissionRate,
                'defaultCommissionType' => $defaultCommissionType,
                'cookieDuration' => $cookieDuration,
                'allowSelfReferral' => $allowSelfReferral,
                'enableCouponCreation' => $enableCouponCreation,
                'programStatus' => $programStatus,
            ], static fn($v) => $v !== null);

            if ($fields === []) {
                return ['error' => 'Provide at least one field to update.'];
            }
            foreach ($fields as $field => $value) {
                $program->$field = $value;
            }

            if (!KickBack::getInstance()->programs->saveProgram($program)) {
                return ['error' => 'Failed to update program.', 'validationErrors' => $program->getErrors()];
            }

            return ['success' => true, 'program' => Presenter::program($program)];
        });
    }
}
