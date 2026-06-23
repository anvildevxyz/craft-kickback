# Changelog

## 1.1.2 - 2026-06-23

### Changed
- Rebuilt the reports page on native Craft CP components (panes, `data` tables, status badges, `btngroup` date presets), matching the dashboard.
- Trimmed `kickback-cp.css` from 966 to 276 lines now that the dashboard and reports no longer use the bespoke styling; CP form/fraud/tag utilities are unchanged.

## 1.1.1 - 2026-06-23

### Changed
- Rebuilt the admin dashboard on native Craft CP components (panes, `data` tables, status badges, design tokens) in place of the bespoke `kb-*` styling, so it now follows CP light/dark theming.

### Fixed
- Uneven dashboard stat-pane heights caused by Craft's `.pane:first-child`/`:last-child` margin resets inside the grid.

## 1.1.0 - 2026-06-18

### Added
- MCP integration: Kickback now registers 25 tools with the optional [craft-mcp](https://github.com/stimmtdigital/craft-mcp) plugin, exposing affiliates, affiliate groups, programs, commission rules, referrals, commissions, payouts and reporting to AI assistants. The dependency is soft (`class_exists`-guarded) — Kickback runs unchanged when craft-mcp is absent. See `src/mcp/`.
- MCP authorization setting (Settings → Integrations): **Allow MCP write operations** (`mcpWriteEnabled`), **off by default**.

### Security
- The MCP surface is **read-first**: list/get/report tools are always available; the create/update tools (affiliates, programs, commission rules) are default-deny behind `mcpWriteEnabled`, and in a web context the matching `kickback-manage*` permission is also required.
- **Money-moving and approval operations are never exposed over MCP** — payouts (create/process/complete/reverse), commission approval/reversal, and affiliate status transitions all remain Control-Panel only.
- Customer/affiliate PII is redacted by default in MCP responses: affiliate PayPal/Stripe identifiers, referral customer email/id and tracking sub-id, and payout gateway transaction/batch references are masked unless a caller explicitly opts out.
- List tools clamp their page size to a hard ceiling (200) to prevent unbounded result sets.