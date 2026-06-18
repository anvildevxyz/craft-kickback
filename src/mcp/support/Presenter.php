<?php

namespace anvildev\craftkickback\mcp\support;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\AffiliateGroupElement;
use anvildev\craftkickback\elements\CommissionElement;
use anvildev\craftkickback\elements\CommissionRuleElement;
use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\elements\ProgramElement;
use anvildev\craftkickback\elements\ReferralElement;
use craft\base\ElementInterface;

/**
 * Serialises Kickback elements into plain, MCP-friendly arrays.
 *
 * Every value returned here is JSON-safe (scalars, arrays, ISO-8601 date
 * strings). Presenters are the single place that decides which fields are
 * exposed over the protocol, and customer/affiliate PII (emails, payment-account
 * identifiers) is masked by default — a caller has to explicitly opt out.
 */
final class Presenter
{
    /**
     * Recursively coerce a value into something json_encode can always handle.
     * Live Craft elements collapse to a compact {id, title} stub; dates become
     * ISO-8601; non-finite floats become null; unknown objects collapse to a
     * string or an opaque class stub (never dumped via get_object_vars).
     */
    public static function jsonSafe(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 16) {
            return null;
        }
        if ($value instanceof ElementInterface) {
            return ['id' => $value->id, 'title' => $value->title ?? (string)$value];
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (is_array($value)) {
            return array_map(static fn($v) => self::jsonSafe($v, $depth + 1), $value);
        }
        if ($value instanceof \JsonSerializable) {
            return self::jsonSafe($value->jsonSerialize(), $depth + 1);
        }
        if ($value instanceof \stdClass) {
            return self::jsonSafe(get_object_vars($value), $depth + 1);
        }
        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string)$value : ['_class' => $value::class];
        }
        if (is_float($value) && !is_finite($value)) {
            return null;
        }
        return $value;
    }

    /**
     * Mask an email to its first two chars + domain, e.g. ja***@example.com.
     */
    public static function redactEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return $email;
        }
        $at = strpos($email, '@');
        if ($at === false) {
            return '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at);
        $keep = substr($local, 0, 2);
        return $keep . '***' . $domain;
    }

    /**
     * Mask a payment-account identifier (PayPal email, Stripe acct id, gateway
     * ref) to its last 4 chars, e.g. *******_abcd.
     */
    public static function redactAccount(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (strlen($value) <= 4) {
            return '***';
        }
        return str_repeat('*', max(3, strlen($value) - 4)) . substr($value, -4);
    }

    /**
     * @param bool $redactPii Mask affiliate payment identifiers. Defaults to true:
     *                        the MCP surface never returns raw payout emails/account
     *                        ids unless a caller explicitly opts out, so a forgotten
     *                        flag fails safe.
     * @return array<string, mixed>
     */
    public static function affiliate(AffiliateElement $a, bool $redactPii = true): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'userId' => $a->userId,
            'programId' => $a->programId,
            'affiliateStatus' => $a->affiliateStatus,
            'referralCode' => $a->referralCode,
            'commissionRateOverride' => $a->commissionRateOverride,
            'commissionTypeOverride' => $a->commissionTypeOverride,
            'parentAffiliateId' => $a->parentAffiliateId,
            'tierLevel' => $a->tierLevel,
            'groupId' => $a->groupId,
            'payoutMethod' => $a->payoutMethod,
            'payoutThreshold' => $a->payoutThreshold,
            'lifetimeEarnings' => $a->lifetimeEarnings,
            'lifetimeReferrals' => $a->lifetimeReferrals,
            'pendingBalance' => $a->pendingBalance,
            'notes' => $a->notes,
            'paypalEmail' => $redactPii ? self::redactEmail($a->paypalEmail) : $a->paypalEmail,
            'stripeAccountId' => $redactPii ? self::redactAccount($a->stripeAccountId) : $a->stripeAccountId,
            'dateApproved' => $a->dateApproved,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function program(ProgramElement $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'handle' => $p->handle,
            'description' => $p->description,
            'defaultCommissionRate' => $p->defaultCommissionRate,
            'defaultCommissionType' => $p->defaultCommissionType,
            'cookieDuration' => $p->cookieDuration,
            'allowSelfReferral' => $p->allowSelfReferral,
            'enableCouponCreation' => $p->enableCouponCreation,
            'programStatus' => $p->programStatus,
            'termsAndConditions' => $p->termsAndConditions,
            'siteId' => $p->siteId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function commissionRule(CommissionRuleElement $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'programId' => $r->programId,
            'type' => $r->type,
            'targetId' => $r->targetId,
            'commissionRate' => $r->commissionRate,
            'commissionType' => $r->commissionType,
            'tierThreshold' => $r->tierThreshold,
            'tierLevel' => $r->tierLevel,
            'lookbackDays' => $r->lookbackDays,
            'priority' => $r->priority,
            'conditions' => $r->conditions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function affiliateGroup(AffiliateGroupElement $g): array
    {
        return [
            'id' => $g->id,
            'name' => $g->name,
            'handle' => $g->handle,
            'commissionRate' => $g->commissionRate,
            'commissionType' => $g->commissionType,
            'sortOrder' => $g->sortOrder,
        ];
    }

    /**
     * @param bool $redactPii Mask the customer email/id and tracking sub-id. Defaults to true.
     * @return array<string, mixed>
     */
    public static function referral(ReferralElement $r, bool $redactPii = true): array
    {
        return [
            'id' => $r->id,
            'affiliateId' => $r->affiliateId,
            'programId' => $r->programId,
            'orderId' => $r->orderId,
            'clickId' => $r->clickId,
            'customerEmail' => $redactPii ? self::redactEmail($r->customerEmail) : $r->customerEmail,
            'customerId' => $redactPii ? null : $r->customerId,
            'orderSubtotal' => $r->orderSubtotal,
            'referralStatus' => $r->referralStatus,
            'attributionMethod' => $r->attributionMethod,
            'couponCode' => $r->couponCode,
            'subId' => $redactPii ? self::redactAccount($r->subId) : $r->subId,
            'fraudFlags' => $r->fraudFlags,
            'dateApproved' => $r->dateApproved,
            'datePaid' => $r->datePaid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function commission(CommissionElement $c): array
    {
        return [
            'id' => $c->id,
            'referralId' => $c->referralId,
            'affiliateId' => $c->affiliateId,
            'amount' => $c->amount,
            'originalAmount' => $c->originalAmount,
            'currency' => $c->currency,
            'rate' => $c->rate,
            'rateType' => $c->rateType,
            'ruleApplied' => $c->ruleApplied,
            'tier' => $c->tier,
            'commissionStatus' => $c->commissionStatus,
            'payoutId' => $c->payoutId,
            'description' => $c->description,
            'dateApproved' => $c->dateApproved,
            'dateReversed' => $c->dateReversed,
        ];
    }

    /**
     * @param bool $redactPii Mask the gateway transaction/batch identifiers. Defaults to true.
     * @return array<string, mixed>
     */
    public static function payout(PayoutElement $p, bool $redactPii = true): array
    {
        return [
            'id' => $p->id,
            'affiliateId' => $p->affiliateId,
            'createdByUserId' => $p->createdByUserId,
            'amount' => $p->amount,
            'currency' => $p->currency,
            'method' => $p->method,
            'payoutStatus' => $p->payoutStatus,
            'transactionId' => $redactPii ? self::redactAccount($p->transactionId) : $p->transactionId,
            'gatewayBatchId' => $redactPii ? self::redactAccount($p->gatewayBatchId) : $p->gatewayBatchId,
            'notes' => $p->notes,
            'processedAt' => $p->processedAt,
        ];
    }
}
