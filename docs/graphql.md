# GraphQL

Kickback exposes all 7 element types through Craft's GraphQL endpoint.
Query names are prefixed with `kickback` to avoid clashing with core
or Commerce types.

## Queries

| List query | Single query | Element |
|---|---|---|
| `kickbackAffiliates` | `kickbackAffiliate` | AffiliateElement |
| `kickbackPrograms` | `kickbackProgram` | ProgramElement |
| `kickbackReferrals` | `kickbackReferral` | ReferralElement |
| `kickbackCommissions` | `kickbackCommission` | CommissionElement |
| `kickbackPayouts` | `kickbackPayout` | PayoutElement |
| `kickbackAffiliateGroups` | `kickbackAffiliateGroup` | AffiliateGroupElement |
| `kickbackCommissionRules` | `kickbackCommissionRule` | CommissionRuleElement |

Each list query accepts the arguments its element query class supports
(status, affiliate filter, program filter, etc). See the individual
`src/gql/arguments/elements/*.php` files for the exact list per type.

## Public-schema redaction

The public schema (Craft's default unauthenticated endpoint) is treated
as untrusted. Sensitive fields and queries are filtered for public
callers:

**Fields redacted to `null` for public callers:**

- AffiliateInterface: `pendingBalance`, `lifetimeEarnings`, `paypalEmail`,
  `payoutMethod`
- ReferralInterface: `customerEmail`, `orderSubtotal` (note: `subId` - optional campaign sub-identifier carried from the tracking click through to the referral - is exposed as a plain `String` field and is not redacted)
- CommissionInterface: `amount`, `originalAmount`, `rate`, `rateType`
- AffiliateGroupInterface: `commissionRate`, `commissionType`
- CommissionRuleInterface: `commissionRate`, `commissionType`
- ProgramInterface: `defaultCommissionRate`, `defaultCommissionType`
- PayoutInterface: every financial field

**Queries that return empty for public callers:**

- `kickbackPayouts`, `kickbackPayout`
- `kickbackCommissions`, `kickbackCommission`
- `kickbackReferrals`, `kickbackReferral`

**`kickbackAffiliates`** is additionally filtered to only return affiliates
with `affiliateStatus = 'active'`. Inactive / pending / suspended affiliates
are hidden from public callers regardless of the requested status argument.

Programs, affiliate groups, and commission rules are unfiltered. Their
field definitions are redacted for rate cards only.
