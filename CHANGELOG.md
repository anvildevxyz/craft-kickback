# Changelog

## 1.1.0 - 2026-06-18

### Added
- MCP integration: Kickback now registers 25 tools with the optional [craft-mcp](https://github.com/stimmtdigital/craft-mcp) plugin, exposing affiliates, affiliate groups, programs, commission rules, referrals, commissions, payouts and reporting to AI assistants. The dependency is soft (`class_exists`-guarded) — Kickback runs unchanged when craft-mcp is absent. See `src/mcp/`.
- MCP authorization setting (Settings → Integrations): **Allow MCP write operations** (`mcpWriteEnabled`), **off by default**.

### Security
- The MCP surface is **read-first**: list/get/report tools are always available; the create/update tools (affiliates, programs, commission rules) are default-deny behind `mcpWriteEnabled`, and in a web context the matching `kickback-manage*` permission is also required.
- **Money-moving and approval operations are never exposed over MCP** — payouts (create/process/complete/reverse), commission approval/reversal, and affiliate status transitions all remain Control-Panel only.
- Customer/affiliate PII is redacted by default in MCP responses: affiliate PayPal/Stripe identifiers, referral customer email/id and tracking sub-id, and payout gateway transaction/batch references are masked unless a caller explicitly opts out.
- List tools clamp their page size to a hard ceiling (200) to prevent unbounded result sets.