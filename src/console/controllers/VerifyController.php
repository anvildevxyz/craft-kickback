<?php

declare(strict_types=1);

namespace anvildev\craftkickback\console\controllers;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\AffiliateGroupElement;
use anvildev\craftkickback\elements\CommissionElement;
use anvildev\craftkickback\elements\CommissionRuleElement;
use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\elements\ProgramElement;
use anvildev\craftkickback\elements\ReferralElement;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\CommissionRecord;
use anvildev\craftkickback\records\CouponRecord;
use anvildev\craftkickback\records\CustomerLinkRecord;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\commerce\elements\Order;
use craft\console\Controller;
use craft\elements\User;
use craft\helpers\Json;
use yii\console\ExitCode;

/**
 * End-to-end verification scenarios for Kickback. Each action exercises a
 * real service path against the live database and asserts expected state.
 *
 * Repeatable: every scenario seeds under a unique program handle prefix
 * ("verify-<scenario>") and tears it down on exit unless --keep is set.
 *
 * Usage:
 *   php craft kickback/verify/all                # run every scenario
 *   php craft kickback/verify/all --only=tiered  # one scenario
 *   php craft kickback/verify/percentage         # run a single scenario directly
 *   php craft kickback/verify/all --keep         # leave fixture data behind
 */
class VerifyController extends Controller
{
    /** Leave fixture data behind for inspection instead of cleaning up. */
    public bool $keep = false;

    /** Run only the named scenario when calling actionAll(). */
    public ?string $only = null;

    /**
     * @var array<string, array{passed:bool, asserts:int, failures:list<string>, error:?string, durationMs:int}>
     */
    private array $results = [];

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['keep', 'only']);
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            $this->stderr("kickback/verify refuses to run outside devMode.\n");
            $this->stderr("Set CRAFT_DEV_MODE=true (or general.php devMode=true) to enable.\n");
            return false;
        }

        return true;
    }

    /** @return array<string, callable(): array{0:int, 1:list<string>}> */
    private function getScenarios(): array
    {
        return [
            'percentage' => fn() => $this->scenarioPercentage(),
            'flat' => fn() => $this->scenarioFlat(),
            'tiered' => fn() => $this->scenarioTiered(),
            'group' => fn() => $this->scenarioGroupOverride(),
            'bonus' => fn() => $this->scenarioBonus(),
            'mlm' => fn() => $this->scenarioMlm(),
            'coupon' => fn() => $this->scenarioCoupon(),
            'refund' => fn() => $this->scenarioRefund(),
            'payout' => fn() => $this->scenarioPayout(),
            'self-referral' => fn() => $this->scenarioSelfReferral(),
            'fraud' => fn() => $this->scenarioFraud(),
            'create-commission' => fn() => $this->scenarioCreateCommission(),
        ];
    }

    public function actionAll(): int
    {
        $this->stdout("\n🔬 Running Kickback verification scenarios\n\n");

        foreach ($this->getScenarios() as $name => $fn) {
            if ($this->only !== null && $this->only !== $name) {
                continue;
            }
            $this->runScenario($name, $fn);
        }

        return $this->printSummary();
    }

    private function runSingle(string $name): int
    {
        $this->runScenario($name, $this->getScenarios()[$name]);
        return $this->printSummary();
    }

    public function actionPercentage(): int
    {
        return $this->runSingle('percentage');
    }
    public function actionFlat(): int
    {
        return $this->runSingle('flat');
    }
    public function actionTiered(): int
    {
        return $this->runSingle('tiered');
    }
    public function actionGroup(): int
    {
        return $this->runSingle('group');
    }
    public function actionBonus(): int
    {
        return $this->runSingle('bonus');
    }
    public function actionMlm(): int
    {
        return $this->runSingle('mlm');
    }
    public function actionCoupon(): int
    {
        return $this->runSingle('coupon');
    }
    public function actionRefund(): int
    {
        return $this->runSingle('refund');
    }
    public function actionPayout(): int
    {
        return $this->runSingle('payout');
    }
    public function actionSelfReferral(): int
    {
        return $this->runSingle('self-referral');
    }
    public function actionFraud(): int
    {
        return $this->runSingle('fraud');
    }
    public function actionCreateCommission(): int
    {
        return $this->runSingle('create-commission');
    }

    /**
     * Baseline percentage commission: program default rate is applied to
     * the order subtotal with no overriding rules in play.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioPercentage(): array
    {
        $a = $this->newAssertions();
        $commissions = KickBack::getInstance()->commissions;

        $program = $this->seedProgram('verify-percentage', defaultRate: 10.0, type: 'percentage');
        $affiliate = $this->seedAffiliate($program, code: 'VERIFY-PCT');

        $a->equals(10.0, $commissions->calculateAmount(100.0, 10.0, 'percentage'), 'percentage math: $100 @ 10%');
        $a->equals(15.0, $commissions->calculateAmount(200.0, 7.5, 'percentage'), 'percentage math: $200 @ 7.5%');
        $a->equals(0.33, $commissions->calculateAmount(3.33, 10.0, 'percentage'), 'percentage rounding: $3.33 @ 10%');

        // The base-rate chain (no override / no bonus / no tier / no group)
        // should fall through to the program default.
        $rules = KickBack::getInstance()->commissionRules;
        $a->null($rules->findBonusRule($program->id), 'no bonus rule active');
        $a->null($rules->findTieredRule($program->id, $affiliate->id), 'no tiered rule matches new affiliate');
        $a->null($affiliate->groupId, 'affiliate has no group');
        $a->equals(10.0, $program->defaultCommissionRate, 'program default rate is 10%');

        return $a->finish();
    }

    /**
     * Flat-rate commissions: a fixed amount per order regardless of subtotal.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioFlat(): array
    {
        $a = $this->newAssertions();
        $commissions = KickBack::getInstance()->commissions;

        $program = $this->seedProgram('verify-flat', defaultRate: 5.0, type: 'flat');

        $a->equals(5.0, $commissions->calculateAmount(100.0, 5.0, 'flat'), 'flat math: any subtotal → flat $5');
        $a->equals(5.0, $commissions->calculateAmount(999.99, 5.0, 'flat'), 'flat is subtotal-independent');
        $a->equals('flat', $program->defaultCommissionType, 'program type stored as flat');

        return $a->finish();
    }

    /**
     * Tiered promotion: an affiliate's referral count crosses a threshold,
     * and findTieredRule returns the higher-tier rule. Crucially, the rule
     * lookup counts REAL referral records, not the lifetimeReferrals field.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioTiered(): array
    {
        $a = $this->newAssertions();
        $rules = KickBack::getInstance()->commissionRules;

        $program = $this->seedProgram('verify-tiered', defaultRate: 10.0);
        $affiliate = $this->seedAffiliate($program, code: 'VERIFY-TIER');

        $this->seedRule($program, type: 'tiered', name: 'Silver: 3+', rate: 15.0, priority: 10, attrs: ['tierThreshold' => 3]);
        $this->seedRule($program, type: 'tiered', name: 'Gold: 5+', rate: 20.0, priority: 11, attrs: ['tierThreshold' => 5]);

        $a->null($rules->findTieredRule($program->id, $affiliate->id), 'no tier matches with 0 referrals');

        $this->seedReferrals($affiliate, $program, count: 3);
        $silver = $rules->findTieredRule($program->id, $affiliate->id);
        $a->notNull($silver, 'silver tier matches with 3 referrals');
        $a->equals(15.0, $silver?->commissionRate, 'silver rate is 15%');

        $this->seedReferrals($affiliate, $program, count: 2); // total = 5
        $gold = $rules->findTieredRule($program->id, $affiliate->id);
        $a->notNull($gold, 'gold tier matches with 5 referrals');
        $a->equals(20.0, $gold?->commissionRate, 'gold rate is 20% (highest threshold wins)');

        return $a->finish();
    }

    /**
     * Affiliate group override: an affiliate assigned to a group inherits
     * the group's rate ahead of the program default.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioGroupOverride(): array
    {
        $a = $this->newAssertions();
        $groups = KickBack::getInstance()->affiliateGroups;

        $program = $this->seedProgram('verify-group', defaultRate: 10.0);
        $group = $this->seedGroup('verify-group-vip', name: 'Verify VIP', rate: 25.0);
        $affiliate = $this->seedAffiliate($program, code: 'VERIFY-GRP', overrides: ['groupId' => $group->id]);

        $a->equals($group->id, $affiliate->groupId, 'affiliate linked to group');
        $loaded = $groups->getGroupById($group->id);
        $a->notNull($loaded, 'group is retrievable');
        $a->equals(25.0, $loaded?->commissionRate, 'group rate is 25%');
        $a->true($loaded?->commissionRate > $program->defaultCommissionRate, 'group rate beats program default');

        return $a->finish();
    }

    /**
     * Bonus rule with an active time window beats both group and program
     * defaults. Outside the window, it must NOT apply.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioBonus(): array
    {
        $a = $this->newAssertions();
        $rules = KickBack::getInstance()->commissionRules;

        $program = $this->seedProgram('verify-bonus', defaultRate: 10.0);

        $past = (new \DateTime('-10 days'))->format(\DateTimeInterface::ATOM);
        $expired = (new \DateTime('-1 day'))->format(\DateTimeInterface::ATOM);
        $future = (new \DateTime('+10 days'))->format(\DateTimeInterface::ATOM);

        $this->seedRule($program, type: 'bonus', name: 'Expired', rate: 99.0, priority: 100, attrs: [
            'conditions' => Json::encode(['startDate' => $past, 'endDate' => $expired]),
        ]);
        $a->null($rules->findBonusRule($program->id), 'expired bonus must not match');

        $this->seedRule($program, type: 'bonus', name: 'Active', rate: 30.0, priority: 50, attrs: [
            'conditions' => Json::encode(['startDate' => $past, 'endDate' => $future]),
        ]);
        $active = $rules->findBonusRule($program->id);
        $a->notNull($active, 'active bonus matches');
        $a->equals(30.0, $active?->commissionRate, 'active bonus rate is 30%');

        return $a->finish();
    }

    /**
     * MLM multi-tier: a child affiliate's order should generate commissions
     * for the parent affiliate based on the mlm_tier rule for that level.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioMlm(): array
    {
        $a = $this->newAssertions();
        $rules = KickBack::getInstance()->commissionRules;

        $program = $this->seedProgram('verify-mlm', defaultRate: 10.0);
        $parent = $this->seedAffiliate($program, code: 'VERIFY-MLM-P');
        $child = $this->seedAffiliate($program, code: 'VERIFY-MLM-C', overrides: ['parentAffiliateId' => $parent->id]);

        $this->seedRule($program, type: 'mlm_tier', name: 'Tier 2', rate: 5.0, priority: 5, attrs: ['tierLevel' => 2]);

        $a->equals($parent->id, $child->parentAffiliateId, 'child links to parent');
        $tier2 = $rules->findMlmTierRule($program->id, 2);
        $a->notNull($tier2, 'tier-2 mlm rule found');
        $a->equals(5.0, $tier2?->commissionRate, 'tier-2 rate is 5%');

        // Simulated commission math: child gets program default 10% on $200 = $20,
        // parent gets tier-2 5% on $200 = $10.
        $commissions = KickBack::getInstance()->commissions;
        $a->equals(20.0, $commissions->calculateAmount(200.0, 10.0, 'percentage'), 'child commission: $200 @ 10%');
        $a->equals(10.0, $commissions->calculateAmount(200.0, 5.0, 'percentage'), 'parent tier-2 commission: $200 @ 5%');

        return $a->finish();
    }

    /**
     * Coupon attribution: an affiliate's coupon code resolves back to that
     * affiliate via TrackingService::resolveAffiliateFromCoupon.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioCoupon(): array
    {
        $a = $this->newAssertions();
        $tracking = KickBack::getInstance()->tracking;

        $program = $this->seedProgram('verify-coupon', defaultRate: 10.0);
        $affiliate = $this->seedAffiliate($program, code: 'VERIFY-CPN');

        $code = 'VERIFY-CPN10';
        $coupon = new CouponRecord();
        $coupon->affiliateId = $affiliate->id;
        $coupon->discountId = 1;
        $coupon->code = $code;
        $coupon->isVanity = true;
        $a->true($coupon->save(false), 'coupon record persisted');

        $resolved = $tracking->resolveAffiliateFromCoupon($code);
        $a->notNull($resolved, 'coupon resolves to an affiliate');
        $a->equals($affiliate->id, $resolved?->id, 'coupon resolves to the right affiliate');

        $missing = $tracking->resolveAffiliateFromCoupon('NOPE-DOES-NOT-EXIST');
        $a->null($missing, 'unknown coupon resolves to null');

        return $a->finish();
    }

    /**
     * Refund reversal: a fully-approved commission is reversed proportionally
     * by reverseCommissionsProportionally. The pending balance must drop by
     * the reversed amount.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioRefund(): array
    {
        $a = $this->newAssertions();
        $commissions = KickBack::getInstance()->commissions;
        $affiliates = KickBack::getInstance()->affiliates;

        $program = $this->seedProgram('verify-refund', defaultRate: 10.0);
        $affiliate = $this->seedAffiliate($program, code: 'VERIFY-RFD');

        $referral = $this->seedReferralElement($affiliate, $program, subtotal: 100.0, status: 'approved');
        $commission = $this->seedCommissionElement($referral, $affiliate, amount: 10.0, rate: 10.0, status: 'approved');

        $affiliates->addPendingBalance($affiliate, 10.0);
        $beforeBalance = $this->reloadAffiliate($affiliate->id)->pendingBalance;
        $a->equals(10.0, $beforeBalance, 'pending balance starts at 10.00');

        $record = CommissionRecord::findOne($commission->id);
        $a->notNull($record, 'commission record fetched');

        // 50% refund → reverse 50% of the commission.
        $commissions->reverseCommissionsProportionally([$record], 0.5);

        $afterRecord = CommissionRecord::findOne($commission->id);
        $a->notNull($afterRecord, 'commission still exists after partial reversal');

        $afterBalance = $this->reloadAffiliate($affiliate->id)->pendingBalance;
        $a->equals(5.0, $afterBalance, 'pending balance reduced by reversed half (5.00)');

        return $a->finish();
    }

    /**
     * Payout flow: approved commissions add to pending balance, payout
     * creation locks the balance, completion moves it to lifetimeEarnings.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioPayout(): array
    {
        $a = $this->newAssertions();
        $payouts = KickBack::getInstance()->payouts;
        $affiliates = KickBack::getInstance()->affiliates;
        $settings = KickBack::getInstance()->getSettings();

        // Toggle in-memory so the $25 test balance exceeds the payout minimum.
        $previousMin = $settings->minimumPayoutAmount;
        $settings->minimumPayoutAmount = 10.0;

        try {
            $program = $this->seedProgram('verify-payout', defaultRate: 10.0);
            $affiliate = $this->seedAffiliate($program, code: 'VERIFY-PAY', overrides: [
                'payoutMethod' => 'paypal',
                'paypalEmail' => 'verify-pay@example.com',
                'payoutThreshold' => 10.0,
            ]);

            $referral = $this->seedReferralElement($affiliate, $program, subtotal: 250.0, status: 'approved');
            $this->seedCommissionElement($referral, $affiliate, amount: 25.0, rate: 10.0, status: 'approved');
            $affiliates->addPendingBalance($affiliate, 25.0);

            $reloaded = $this->reloadAffiliate($affiliate->id);
            $a->equals(25.0, $reloaded->pendingBalance, 'pending balance is 25.00 before payout');
            $a->equals(0.0, (float)$reloaded->lifetimeEarnings, 'lifetime earnings starts at 0.00');

            $payout = $payouts->createPayout($reloaded, 'verify scenario');
            $a->notNull($payout, 'payout created');
            $a->equals(25.0, (float)$payout?->amount, 'payout amount captures current balance');
            $a->equals('pending', $payout?->payoutStatus, 'payout starts pending');

            // createPayout intentionally does NOT deduct the balance - it relies on
            // the pending payout row + SELECT FOR UPDATE to prevent double-spends,
            // and only deducts at completion via recordPayout.
            $afterCreate = $this->reloadAffiliate($affiliate->id);
            $a->equals(25.0, (float)$afterCreate->pendingBalance, 'pending balance unchanged after payout creation');

            // A second createPayout must refuse - there's already an active payout.
            $duplicate = $payouts->createPayout($afterCreate, 'verify duplicate');
            $a->null($duplicate, 'second createPayout refuses while one is active');

            if ($payout === null) {
                return $a->finish();
            }

            $payouts->completePayout($payout, 'TXN-VERIFY-1');
            $afterComplete = $this->reloadAffiliate($affiliate->id);
            $a->equals(25.0, (float)$afterComplete->lifetimeEarnings, 'lifetime earnings credited after completion');
            $a->equals(0.0, (float)$afterComplete->pendingBalance, 'pending balance drained at completion');

            return $a->finish();
        } finally {
            $settings->minimumPayoutAmount = $previousMin;
        }
    }

    /**
     * Self-referral guard: when an affiliate's own user buys via lifetime
     * customer-link attribution, processOrder must refuse the referral while
     * allowSelfReferral is false - and create one once the flag flips.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioSelfReferral(): array
    {
        $a = $this->newAssertions();
        $referrals = KickBack::getInstance()->referrals;
        $settings = KickBack::getInstance()->getSettings();

        $program = $this->seedProgram('verify-selfref', defaultRate: 10.0);
        $a->false($program->allowSelfReferral, 'program starts with self-referral disallowed');

        $user = $this->getOrCreateTestUser('verify-selfref@test.example.com', 'Verify', 'SelfRef');
        $affiliate = $this->seedAffiliate($program, code: 'VERIFY-SELF', overrides: ['userId' => $user->id]);

        // Customer-link attribution avoids needing a click cookie in CLI.
        $link = new CustomerLinkRecord();
        $link->affiliateId = $affiliate->id;
        $link->customerId = $user->id;
        $link->customerEmail = $user->email;
        $a->true($link->save(false), 'customer link persisted');

        // Toggle in-memory only - settings revert when the process ends.
        $previousLifetime = $settings->enableLifetimeCommissions;
        $settings->enableLifetimeCommissions = true;

        $order = $this->buildStubOrder($user);

        try {
            $blocked = $referrals->processOrder($order);
            $a->null($blocked, 'processOrder returns null when self-referral disallowed');
            $a->false(
                ReferralRecord::find()->where(['orderId' => $order->id])->exists(),
                'no referral persisted when self-referral disallowed',
            );

            // Now flip the program flag and try again - same order would normally
            // be blocked by orderHasReferral, so use a fresh stub order.
            $program->allowSelfReferral = true;
            $this->saveOrFail($program, 'Program (allow self-referral)');

            $order2 = $this->buildStubOrder($user);
            $allowed = $referrals->processOrder($order2);
            $a->notNull($allowed, 'processOrder creates referral once self-referral allowed');
            $a->equals(
                'lifetime_customer',
                $allowed?->attributionMethod,
                'attribution method is lifetime_customer',
            );
            $a->equals($affiliate->id, $allowed?->affiliateId, 'referral attributed to the right affiliate');
        } finally {
            $settings->enableLifetimeCommissions = $previousLifetime;
            $this->deleteStubOrder($order);
            if (isset($order2)) {
                $this->deleteStubOrder($order2);
            }
        }

        return $a->finish();
    }

    /**
     * Fraud detection: a second referral from the same customer to the same
     * affiliate must be flagged for duplicate_customer, and flagReferral
     * must persist the flagged status + flag list.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioFraud(): array
    {
        $a = $this->newAssertions();
        $fraud = KickBack::getInstance()->fraud;
        $settings = KickBack::getInstance()->getSettings();

        $program = $this->seedProgram('verify-fraud', defaultRate: 10.0);
        $affiliate = $this->seedAffiliate($program, code: 'VERIFY-FRD');

        $previousFraud = $settings->enableFraudDetection;
        $settings->enableFraudDetection = true;

        try {
            // First referral: clean baseline.
            $first = $this->seedReferralElement($affiliate, $program, subtotal: 50.0, status: 'approved');
            $first->customerEmail = 'fraud-victim@example.com';
            $this->saveOrFail($first, 'Referral (first)');
            $firstRecord = ReferralRecord::findOne($first->id);
            $a->notNull($firstRecord, 'first referral record fetched');

            $cleanFlags = $fraud->evaluateReferral($firstRecord);
            $a->equals(0, count($cleanFlags), 'first referral is clean');

            // Second referral: same affiliate + same customer email → duplicate.
            $second = $this->seedReferralElement($affiliate, $program, subtotal: 75.0, status: 'pending');
            $second->customerEmail = 'fraud-victim@example.com';
            $this->saveOrFail($second, 'Referral (second)');
            $secondRecord = ReferralRecord::findOne($second->id);
            $a->notNull($secondRecord, 'second referral record fetched');

            $flags = $fraud->evaluateReferral($secondRecord);
            $a->true(count($flags) > 0, 'duplicate customer triggers at least one flag');
            $hasDuplicate = false;
            foreach ($flags as $flag) {
                if (str_starts_with($flag, 'duplicate_customer')) {
                    $hasDuplicate = true;
                    break;
                }
            }
            $a->true($hasDuplicate, 'flags include duplicate_customer');

            // flagReferral must persist the status + JSON-encoded flag list.
            $a->true($fraud->flagReferral($secondRecord, $flags), 'flagReferral returns true');
            $reloaded = ReferralRecord::findOne($second->id);
            $a->equals('flagged', $reloaded?->status, 'referral status is flagged');
            $a->notNull($reloaded?->fraudFlags, 'fraudFlags JSON persisted');
        } finally {
            $settings->enableFraudDetection = $previousFraud;
        }

        return $a->finish();
    }

    /**
     * End-to-end commission creation: build a real Commerce Order with a real
     * purchasable line item, run it through createReferral + createCommission,
     * and assert the line-item iteration + base-rate chain produce the right
     * commission. This is the only scenario that exercises createCommission()'s
     * line-item loop with actual purchasables - the rest hit the building
     * blocks (calculateAmount, rule finders) directly.
     *
     * @return array{0: int, 1: list<string>}
     */
    private function scenarioCreateCommission(): array
    {
        $a = $this->newAssertions();
        $referrals = KickBack::getInstance()->referrals;
        $commissions = KickBack::getInstance()->commissions;

        // Find any existing purchasable. Without one, we can't build a line
        // item, so the scenario is structurally untestable on this install.
        $purchasable = \craft\commerce\elements\Variant::find()->status(null)->one();
        if ($purchasable === null) {
            $a->true(false, 'requires at least one Commerce purchasable in the database');
            return $a->finish();
        }

        $program = $this->seedProgram('verify-create-commission', defaultRate: 12.0);
        $affiliate = $this->seedAffiliate($program, code: 'VERIFY-E2E');
        $user = $this->getOrCreateTestUser('verify-e2e-customer@test.example.com', 'Verify', 'E2E');

        $order = null;
        try {
            $order = $this->buildStubOrderWithLineItem($user, (int)$purchasable->id);
            $a->notNull($order->id, 'order persisted with id');
            $a->true(count($order->getLineItems()) > 0, 'order has at least one line item');

            $itemSubtotal = (float)$order->getItemSubtotal();
            $a->true($itemSubtotal > 0, "item subtotal is positive (got {$itemSubtotal})");

            $referral = $referrals->createReferral(
                $affiliate,
                $order,
                $itemSubtotal,
                null,
                'cookie',
                null,
            );
            $a->notNull($referral, 'referral created from real order');
            if ($referral === null) {
                return $a->finish();
            }

            $commission = $commissions->createCommission($referral, $affiliate, $order);
            $a->notNull($commission, 'commission created from real order');
            if ($commission === null) {
                return $a->finish();
            }

            // Program default rate is 12% with no overriding rules, so the
            // commission amount must equal subtotal * 0.12, rounded to currency.
            $expected = $commissions->roundMoney($itemSubtotal * 0.12);
            $a->equals($expected, (float)$commission->amount, "commission = subtotal * 12% (expected {$expected})");
            $a->equals(12.0, (float)$commission->rate, 'rate is 12% from program default');
            $a->equals('percentage', $commission->rateType, 'rate type is percentage');
            $a->equals($affiliate->id, (int)$commission->affiliateId, 'commission attributed to seeded affiliate');
            $a->equals($referral->id, (int)$commission->referralId, 'commission linked to seeded referral');
        } finally {
            if ($order !== null) {
                $this->deleteStubOrder($order);
            }
        }

        return $a->finish();
    }

    private function runScenario(string $name, callable $fn): void
    {
        $this->stdout("→ {$name} ");
        $start = microtime(true);
        $asserts = 0;
        $failures = [];
        $error = null;

        try {
            [$asserts, $failures] = $fn();
        } catch (\Throwable $e) {
            $error = $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
        } finally {
            if (!$this->keep) {
                try {
                    $this->cleanup($name);
                } catch (\Throwable $cleanupError) {
                    $this->stderr(" (cleanup warning: {$cleanupError->getMessage()})");
                }
            }
        }

        $passed = $error === null && empty($failures);
        $this->results[$name] = [
            'passed' => $passed,
            'asserts' => $asserts,
            'failures' => $failures,
            'error' => $error,
            'durationMs' => (int)round((microtime(true) - $start) * 1000),
        ];

        $this->stdout($passed ? "✓\n" : "✗\n");
        foreach ($failures as $f) {
            $this->stderr("    ✗ {$f}\n");
        }
        if ($error !== null) {
            $this->stderr("    ! {$error}\n");
        }
    }

    private function printSummary(): int
    {
        $this->stdout("\n");
        $this->stdout(str_repeat('─', 64) . "\n");
        $this->stdout(sprintf("  %-18s %-8s %-10s %s\n", 'Scenario', 'Status', 'Asserts', 'Time'));
        $this->stdout(str_repeat('─', 64) . "\n");

        $allPassed = true;
        $totalAsserts = 0;
        foreach ($this->results as $name => $r) {
            $status = $r['passed'] ? 'PASS' : 'FAIL';
            $this->stdout(sprintf("  %-18s %-8s %-10d %dms\n", $name, $status, $r['asserts'], $r['durationMs']));
            if (!$r['passed']) {
                $allPassed = false;
            }
            $totalAsserts += $r['asserts'];
        }
        $this->stdout(str_repeat('─', 64) . "\n");
        $this->stdout(sprintf("  %d scenario(s), %d assertion(s)\n\n", count($this->results), $totalAsserts));

        return $allPassed ? ExitCode::OK : ExitCode::SOFTWARE;
    }

    private function seedProgram(string $handle, float $defaultRate, string $type = 'percentage'): ProgramElement
    {
        $handle = $this->normalizeHandle($handle);
        $this->cleanupProgram($handle); // start fresh on every run

        $program = new ProgramElement();
        $program->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $program->name = ucfirst(str_replace('-', ' ', $handle));
        $program->handle = $handle;
        $program->description = 'Auto-generated by kickback/verify';
        $program->defaultCommissionRate = $defaultRate;
        $program->defaultCommissionType = $type;
        $program->cookieDuration = 30;
        $program->allowSelfReferral = false;
        $program->programStatus = 'active';
        $this->saveOrFail($program, 'Program');

        return $program;
    }

    /**
     * Convert a friendly identifier (verify-self-ref) into a Craft-valid
     * handle (verifySelfRef). Handle pattern is /^[a-zA-Z][a-zA-Z0-9_]*$/.
     */
    private function normalizeHandle(string $raw): string
    {
        $parts = preg_split('/[-_\s]+/', $raw) ?: [$raw];
        $first = strtolower((string)array_shift($parts));
        $rest = array_map(fn($p) => ucfirst(strtolower($p)), $parts);
        return $first . implode('', $rest);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedAffiliate(ProgramElement $program, string $code, array $overrides = []): AffiliateElement
    {
        $email = strtolower($code) . '@verify.example.com';
        $user = $this->getOrCreateTestUser($email, 'Verify', $code);

        $affiliate = new AffiliateElement();
        $affiliate->title = "Verify {$code}";
        $affiliate->userId = $overrides['userId'] ?? $user->id;
        $affiliate->programId = $program->id;
        $affiliate->affiliateStatus = 'active';
        $affiliate->referralCode = $code;
        $affiliate->groupId = $overrides['groupId'] ?? null;
        $affiliate->parentAffiliateId = $overrides['parentAffiliateId'] ?? null;
        $affiliate->payoutMethod = $overrides['payoutMethod'] ?? 'manual';
        $affiliate->paypalEmail = $overrides['paypalEmail'] ?? null;
        $affiliate->payoutThreshold = $overrides['payoutThreshold'] ?? 50.0;
        $affiliate->lifetimeEarnings = 0;
        $affiliate->lifetimeReferrals = 0;
        $affiliate->pendingBalance = 0;
        $affiliate->dateApproved = new \DateTime();
        $this->saveOrFail($affiliate, 'Affiliate');

        return $affiliate;
    }

    private function seedGroup(string $handle, string $name, float $rate): AffiliateGroupElement
    {
        $handle = $this->normalizeHandle($handle);
        // groups are not program-scoped; reuse if a previous run left it behind
        $existing = AffiliateGroupElement::find()->handle($handle)->one();
        if ($existing !== null) {
            $existing->commissionRate = $rate;
            Craft::$app->getElements()->saveElement($existing);
            return $existing;
        }

        $group = new AffiliateGroupElement();
        $group->name = $name;
        $group->handle = $handle;
        $group->commissionRate = $rate;
        $group->commissionType = 'percentage';
        $group->sortOrder = 99;
        $this->saveOrFail($group, 'AffiliateGroup');

        return $group;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function seedRule(
        ProgramElement $program,
        string $type,
        string $name,
        float $rate,
        int $priority,
        array $attrs = [],
    ): CommissionRuleElement {
        $rule = new CommissionRuleElement();
        $rule->programId = $program->id;
        $rule->name = $name;
        $rule->type = $type;
        $rule->commissionRate = $rate;
        $rule->commissionType = 'percentage';
        $rule->priority = $priority;
        $rule->tierThreshold = $attrs['tierThreshold'] ?? null;
        $rule->tierLevel = $attrs['tierLevel'] ?? null;
        $rule->targetId = $attrs['targetId'] ?? null;
        $rule->lookbackDays = $attrs['lookbackDays'] ?? null;
        $rule->conditions = $attrs['conditions'] ?? null;
        $this->saveOrFail($rule, 'CommissionRule');

        return $rule;
    }

    /**
     * Seed N approved referral records so findTieredRule's count query sees them.
     */
    private function seedReferrals(AffiliateElement $affiliate, ProgramElement $program, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $r = new ReferralElement();
            $r->affiliateId = $affiliate->id;
            $r->programId = $program->id;
            $r->orderId = random_int(900000, 999999);
            $r->customerEmail = "tier-{$i}@verify.example.com";
            $r->orderSubtotal = 50.0;
            $r->referralStatus = 'approved';
            $r->attributionMethod = 'cookie';
            $r->dateApproved = new \DateTime();
            $this->saveOrFail($r, 'Referral (tier seed)');
        }
    }

    private function seedReferralElement(
        AffiliateElement $affiliate,
        ProgramElement $program,
        float $subtotal,
        string $status,
    ): ReferralElement {
        $r = new ReferralElement();
        $r->affiliateId = $affiliate->id;
        $r->programId = $program->id;
        $r->orderId = random_int(900000, 999999);
        $r->customerEmail = 'verify-customer@example.com';
        $r->orderSubtotal = $subtotal;
        $r->referralStatus = $status;
        $r->attributionMethod = 'cookie';
        if ($status === 'approved') {
            $r->dateApproved = new \DateTime();
        }
        $this->saveOrFail($r, 'Referral');
        return $r;
    }

    private function seedCommissionElement(
        ReferralElement $referral,
        AffiliateElement $affiliate,
        float $amount,
        float $rate,
        string $status,
    ): CommissionElement {
        $c = new CommissionElement();
        $c->referralId = $referral->id;
        $c->affiliateId = $affiliate->id;
        $c->amount = $amount;
        $c->originalAmount = $amount;
        $c->currency = KickBack::getCommerceCurrency();
        $c->rate = $rate;
        $c->rateType = 'percentage';
        $c->ruleApplied = 'verify:manual';
        $c->tier = 1;
        $c->commissionStatus = $status;
        if ($status === 'approved') {
            $c->dateApproved = new \DateTime();
        }
        $this->saveOrFail($c, 'Commission');
        return $c;
    }

    /**
     * Save a minimal Commerce Order owned by the given user. Validation is
     * skipped - we only need an Order with an id, customer, and email so
     * processOrder() can resolve attribution and fire its self-referral check.
     */
    private function buildStubOrder(User $user): Order
    {
        $order = new Order();
        $order->setCustomer($user);
        $order->email = $user->email;
        $order->orderSiteId = Craft::$app->getSites()->getPrimarySite()->id;
        $order->number = 'verify-' . bin2hex(random_bytes(8));

        if (!Craft::$app->getElements()->saveElement($order, false)) {
            throw new \RuntimeException('Failed to save stub Order: ' . implode(', ', $order->getFirstErrors()));
        }
        return $order;
    }

    /**
     * Save a stub Commerce Order with one real line item pointing at the
     * given purchasable. Used by the create-commission scenario so
     * createCommission()'s line-item loop has actual purchasables to iterate.
     */
    private function buildStubOrderWithLineItem(User $user, int $purchasableId): Order
    {
        $order = new Order();
        $order->setCustomer($user);
        $order->email = $user->email;
        $order->orderSiteId = Craft::$app->getSites()->getPrimarySite()->id;
        $order->number = 'verify-e2e-' . bin2hex(random_bytes(8));

        $lineItem = \craft\commerce\Plugin::getInstance()->getLineItems()->create($order, [
            'purchasableId' => $purchasableId,
            'qty' => 1,
        ]);
        $order->setLineItems([$lineItem]);

        if (!Craft::$app->getElements()->saveElement($order, false)) {
            throw new \RuntimeException('Failed to save stub Order: ' . implode(', ', $order->getFirstErrors()));
        }

        return $order;
    }

    private function deleteStubOrder(Order $order): void
    {
        if ($order->id !== null) {
            try {
                Craft::$app->getElements()->deleteElement($order, true);
            } catch (\Throwable) {
                // already gone or Commerce refused - non-fatal in cleanup
            }
        }
    }

    private function getOrCreateTestUser(string $email, string $first, string $last): User
    {
        $user = User::find()->email($email)->one();
        if ($user !== null) {
            return $user;
        }

        $user = new User();
        $user->username = preg_replace('/[^a-z0-9]/', '.', strtolower(explode('@', $email)[0]));
        $user->email = $email;
        $user->firstName = $first;
        $user->lastName = $last;
        $user->active = true;
        $user->pending = false;

        if (!Craft::$app->getElements()->saveElement($user)) {
            throw new \RuntimeException("Failed to create test user {$email}: " . implode(', ', $user->getFirstErrors()));
        }
        return $user;
    }

    private function reloadAffiliate(int $id): AffiliateElement
    {
        $affiliate = AffiliateElement::find()->id($id)->status(null)->one();
        if ($affiliate === null) {
            throw new \RuntimeException("Affiliate {$id} disappeared mid-scenario");
        }
        return $affiliate;
    }

    private function saveOrFail(\craft\base\ElementInterface $element, string $label): void
    {
        if (!Craft::$app->getElements()->saveElement($element)) {
            $errors = implode(', ', $element->getFirstErrors());
            throw new \RuntimeException("Failed to save {$label}: {$errors}");
        }
    }

    private function cleanup(string $scenarioName): void
    {
        $handleMap = [
            'self-referral' => 'verify-selfref',
        ];
        $rawHandle = $handleMap[$scenarioName] ?? 'verify-' . $scenarioName;
        $this->cleanupProgram($this->normalizeHandle($rawHandle));

        if ($scenarioName === 'group') {
            $group = AffiliateGroupElement::find()->handle($this->normalizeHandle('verify-group-vip'))->one();
            if ($group !== null) {
                Craft::$app->getElements()->deleteElement($group, true);
            }
        }
    }

    private function cleanupProgram(string $handle): void
    {
        $program = ProgramElement::find()->handle($handle)->status(null)->one();
        if ($program === null) {
            return;
        }

        $affiliateIds = AffiliateElement::find()->programId($program->id)->status(null)->ids();

        if (!empty($affiliateIds)) {
            // Custom element query params only accept scalars; use Yii where()
            // for the IN-list filters.
            $referrals = ReferralElement::find()
                ->where(['affiliateId' => $affiliateIds])
                ->status(null)
                ->all();
            $referralIds = array_map(fn($r) => $r->id, $referrals);
            $orderIds = array_filter(array_map(fn($r) => $r->orderId, $referrals));

            if (!empty($referralIds)) {
                $commissions = CommissionElement::find()
                    ->where(['referralId' => $referralIds])
                    ->status(null)
                    ->all();
                foreach ($commissions as $c) {
                    Craft::$app->getElements()->deleteElement($c, true);
                }
                foreach ($referrals as $r) {
                    Craft::$app->getElements()->deleteElement($r, true);
                }
            }

            $payouts = PayoutElement::find()
                ->where(['affiliateId' => $affiliateIds])
                ->status(null)
                ->all();
            foreach ($payouts as $p) {
                Craft::$app->getElements()->deleteElement($p, true);
            }

            Craft::$app->getDb()->createCommand()
                ->delete('{{%kickback_coupons}}', ['affiliateId' => $affiliateIds])
                ->execute();

            Craft::$app->getDb()->createCommand()
                ->delete('{{%kickback_customer_links}}', ['affiliateId' => $affiliateIds])
                ->execute();

            // Stub Orders created by buildStubOrder() - best-effort cleanup.
            foreach ($orderIds as $orderId) {
                $stub = Order::find()->id((int)$orderId)->status(null)->one();
                if ($stub !== null) {
                    try {
                        Craft::$app->getElements()->deleteElement($stub, true);
                    } catch (\Throwable) {
                        // skip if Commerce refuses
                    }
                }
            }

            foreach (AffiliateElement::find()->id($affiliateIds)->status(null)->all() as $a) {
                Craft::$app->getElements()->deleteElement($a, true);
            }
        }

        foreach (CommissionRuleElement::find()->programId($program->id)->status(null)->all() as $rule) {
            Craft::$app->getElements()->deleteElement($rule, true);
        }

        Craft::$app->getElements()->deleteElement($program, true);
    }

    private function newAssertions(): object
    {
        return new class() {
            public int $count = 0;
            /** @var list<string> */
            public array $failures = [];

            public function equals(mixed $expected, mixed $actual, string $message): void
            {
                $this->count++;
                if (is_float($expected) || is_float($actual)) {
                    if (abs((float)$expected - (float)$actual) > 0.005) {
                        $this->failures[] = "{$message} - expected {$expected}, got {$actual}";
                    }
                    return;
                }
                if ($expected !== $actual) {
                    $expStr = var_export($expected, true);
                    $actStr = var_export($actual, true);
                    $this->failures[] = "{$message} - expected {$expStr}, got {$actStr}";
                }
            }

            public function true(mixed $cond, string $message): void
            {
                $this->count++;
                if ($cond !== true) {
                    $this->failures[] = "{$message} - expected true, got " . var_export($cond, true);
                }
            }

            public function false(mixed $cond, string $message): void
            {
                $this->count++;
                if ($cond !== false) {
                    $this->failures[] = "{$message} - expected false, got " . var_export($cond, true);
                }
            }

            public function null(mixed $value, string $message): void
            {
                $this->count++;
                if ($value !== null) {
                    $this->failures[] = "{$message} - expected null, got " . var_export($value, true);
                }
            }

            public function notNull(mixed $value, string $message): void
            {
                $this->count++;
                if ($value === null) {
                    $this->failures[] = "{$message} - expected non-null, got null";
                }
            }

            /** @return array{0:int, 1:list<string>} */
            public function finish(): array
            {
                return [$this->count, $this->failures];
            }
        };
    }
}
