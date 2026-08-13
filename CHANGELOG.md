# Changelog

## 1.1.3 - 2026-08-13

### Fixed
- **Kickback installs alongside plugins that cap the Stripe SDK lower.** Kickback required `stripe/stripe-php` `^20.0`, which no version of Solspace Freeform accepts — Freeform 5 caps the same SDK at `^15`, Formie at `^16`, and Craft Commerce's Stripe gateway at `^13`. `stripe/stripe-php` is a single shared package, so the lowest ceiling in the project wins and Composer refused the install outright. The requirement is now a range, `^13.0` through `^21.0`. Kickback only uses transfers create/retrieve, Connect accounts create/retrieve, account links create and webhook signature verification, and those signatures are identical across that range.

### Internal
- PHPStan no longer reports unmatched ignore patterns. Several ignores cover packages that may or may not be installed (craft-mcp, Commerce), and the Stripe one only matches on SDK majors that declare strict array shapes for `create()` — so with the range above, `composer phpstan` failed at the floor and passed at the ceiling on identical code. It was already failing on `main` for the same reason, reporting two unmatched patterns and nothing else.
- `StripeSdkSurfaceTest` pins the SDK surface the gateway stands on. The existing `StripeWebhookTest` covers the guard clauses in front of the SDK and stops before reaching it, so nothing in the suite would have noticed the floor breaking.
- The `craftcms/new-release` workflow no longer fails when the GitHub release already exists. It builds the release with `ncipollo/release-action`, which returns 422 on a release created manually right after tagging; `allowUpdates` now lets it update instead ([#4](https://github.com/anvildevxyz/craft-kickback/pull/4)).
- Dependabot now proposes weekly Composer and GitHub Actions updates, with the dev toolchain grouped into one pull request and the `craftcms/cms` range left alone ([#5](https://github.com/anvildevxyz/craft-kickback/pull/5)).

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