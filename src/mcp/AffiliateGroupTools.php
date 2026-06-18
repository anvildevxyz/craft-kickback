<?php

namespace anvildev\craftkickback\mcp;

use anvildev\craftkickback\elements\AffiliateGroupElement;
use anvildev\craftkickback\mcp\support\Presenter;
use Mcp\Capability\Attribute\McpTool;
use stimmt\craft\Mcp\attributes\McpToolMeta;
use stimmt\craft\Mcp\enums\ToolCategory;

/**
 * MCP tools for Kickback affiliate groups (named commission-rate buckets that
 * affiliates can be assigned to). Read-only.
 */
class AffiliateGroupTools
{
    use ToolResponseTrait;

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_list_affiliate_groups',
        description: 'List Kickback affiliate groups (name, handle, commission rate/type), in sort order.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function listAffiliateGroups(int $limit = 50, int $offset = 0): array
    {
        return $this->guard(function() use ($limit, $offset): array {
            $groups = AffiliateGroupElement::find()
                ->siteId('*')
                ->status(null)
                ->orderBy(['kickback_affiliate_groups.sortOrder' => SORT_ASC])
                ->limit($this->clampLimit($limit))
                ->offset($this->clampOffset($offset))
                ->all();

            return [
                'count' => count($groups),
                'groups' => array_map(static fn(AffiliateGroupElement $g) => Presenter::affiliateGroup($g), $groups),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'kickback_get_affiliate_group',
        description: 'Get a single Kickback affiliate group by id.',
    )]
    #[McpToolMeta(category: ToolCategory::PLUGIN)]
    public function getAffiliateGroup(int $id): array
    {
        return $this->guard(function() use ($id): array {
            $group = AffiliateGroupElement::find()->siteId('*')->status(null)->id($id)->one();
            if (!$group instanceof AffiliateGroupElement) {
                return ['error' => "Affiliate group #{$id} not found."];
            }

            return ['group' => Presenter::affiliateGroup($group)];
        });
    }
}
