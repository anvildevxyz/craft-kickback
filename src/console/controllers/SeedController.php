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
use anvildev\craftkickback\records\ClickRecord;
use anvildev\craftkickback\records\CouponRecord;
use anvildev\craftkickback\records\CustomerLinkRecord;
use Craft;
use craft\console\Controller;
use craft\elements\User;
use yii\console\ExitCode;

/**
 * Seeds the database with realistic test data for all Kickback entities.
 *
 * Usage: php craft kickback/seed
 */
class SeedController extends Controller
{
    /**
     * @var bool Whether to clear existing Kickback data before seeding.
     */
    public bool $fresh = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'fresh',
        ]);
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            $this->stderr("kickback/seed refuses to run outside devMode.\n");
            $this->stderr("Set CRAFT_DEV_MODE=true (or general.php devMode=true) to enable.\n");
            return false;
        }

        return true;
    }

    /**
     * Re-save all payouts so element indexes sync with the records table
     * after direct DB writes.
     */
    public function actionResavePayouts(): int
    {
        $payouts = PayoutElement::find()->status(null)->all();
        $total = count($payouts);

        if ($total === 0) {
            $this->stdout("No payouts found.\n");
            return ExitCode::OK;
        }

        $this->stdout("Re-saving {$total} payouts...\n");

        $saved = 0;
        foreach ($payouts as $payout) {
            if (Craft::$app->getElements()->saveElement($payout, false)) {
                $saved++;
            }
        }

        $this->stdout("Re-saved {$saved}/{$total} payouts. Element indexes should now be in sync.\n");
        return ExitCode::OK;
    }

    public function actionIndex(): int
    {
        if ($this->fresh) {
            $this->stdout("Clearing existing Kickback data...\n");
            $this->clearData();
        }

        $this->stdout("\n🌱 Seeding Kickback test data...\n\n");

        $programs = $this->seedPrograms();
        $groups = $this->seedAffiliateGroups();
        $affiliates = $this->seedAffiliates($programs, $groups);
        $rules = $this->seedCommissionRules($programs);
        $clicks = $this->seedClicks($affiliates, $programs);
        $referrals = $this->seedReferrals($affiliates, $programs, $clicks);
        $commissions = $this->seedCommissions($referrals, $affiliates);
        $payouts = $this->seedPayouts($affiliates);
        $this->seedCoupons($affiliates);
        $this->seedCustomerLinks($affiliates);

        $this->stdout("\n✅ Seeding complete!\n");
        $this->stdout("   Programs:         " . count($programs) . "\n");
        $this->stdout("   Affiliate Groups: " . count($groups) . "\n");
        $this->stdout("   Affiliates:       " . count($affiliates) . "\n");
        $this->stdout("   Commission Rules: " . count($rules) . "\n");
        $this->stdout("   Clicks:           " . count($clicks) . "\n");
        $this->stdout("   Referrals:        " . count($referrals) . "\n");
        $this->stdout("   Commissions:      " . count($commissions) . "\n");
        $this->stdout("   Payouts:          " . count($payouts) . "\n\n");

        return ExitCode::OK;
    }

    /**
     * @return list<ProgramElement>
     */
    private function seedPrograms(): array
    {
        $this->stdout("  Seeding programs...\n");

        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;

        $data = [
            ['name' => 'Main Affiliate Program', 'handle' => 'main', 'description' => 'Our primary affiliate program with competitive rates.', 'rate' => 15.0, 'status' => 'active'],
            ['name' => 'Premium Partners', 'handle' => 'premium', 'description' => 'Invite-only program for high-volume partners.', 'rate' => 25.0, 'status' => 'active'],
            ['name' => 'Holiday Campaign 2025', 'handle' => 'holiday2025', 'description' => 'Seasonal promotion with boosted commissions.', 'rate' => 20.0, 'status' => 'inactive'],
            ['name' => 'Legacy Program', 'handle' => 'legacy', 'description' => 'Deprecated program from previous platform.', 'rate' => 10.0, 'status' => 'archived'],
        ];

        $programs = [];
        foreach ($data as $d) {
            $p = new ProgramElement();
            $p->siteId = $primarySiteId;
            $p->name = $d['name'];
            $p->handle = $d['handle'];
            $p->description = $d['description'];
            $p->defaultCommissionRate = $d['rate'];
            $p->defaultCommissionType = 'percentage';
            $p->cookieDuration = 30;
            $p->allowSelfReferral = false;
            $p->programStatus = $d['status'];
            $this->saveOrFail($p, 'Program');
            $programs[] = $p;
        }

        return $programs;
    }

    /**
     * @return list<AffiliateGroupElement>
     */
    private function seedAffiliateGroups(): array
    {
        $this->stdout("  Seeding affiliate groups...\n");

        $data = [
            ['name' => 'Silver', 'handle' => 'silver', 'rate' => 12.0, 'order' => 1],
            ['name' => 'Gold', 'handle' => 'gold', 'rate' => 18.0, 'order' => 2],
            ['name' => 'Platinum', 'handle' => 'platinum', 'rate' => 25.0, 'order' => 3],
            ['name' => 'VIP', 'handle' => 'vip', 'rate' => 30.0, 'order' => 4],
        ];

        $groups = [];
        foreach ($data as $d) {
            $g = new AffiliateGroupElement();
            $g->name = $d['name'];
            $g->handle = $d['handle'];
            $g->commissionRate = $d['rate'];
            $g->commissionType = 'percentage';
            $g->sortOrder = $d['order'];
            $this->saveOrFail($g, 'AffiliateGroup');
            $groups[] = $g;
        }

        return $groups;
    }

    /**
     * @param list<ProgramElement> $programs
     * @param list<AffiliateGroupElement> $groups
     * @return list<AffiliateElement>
     */
    private function seedAffiliates(array $programs, array $groups): array
    {
        $this->stdout("  Seeding affiliates...\n");

        $users = $this->getOrCreateTestUsers();
        $mainProgram = $programs[0];
        $premiumProgram = $programs[1];

        $affiliateData = [
            ['user' => 0, 'code' => 'SARAH2025', 'status' => 'active', 'program' => $mainProgram, 'group' => $groups[2], 'earnings' => 4250.00, 'referrals' => 87, 'pending' => 320.50, 'method' => 'paypal', 'paypal' => 'sarah@example.com'],
            ['user' => 1, 'code' => 'MIKEJ', 'status' => 'active', 'program' => $mainProgram, 'group' => $groups[1], 'earnings' => 1890.00, 'referrals' => 42, 'pending' => 145.00, 'method' => 'stripe', 'stripe' => 'acct_1234567890'],
            ['user' => 2, 'code' => 'EMILYW', 'status' => 'active', 'program' => $premiumProgram, 'group' => $groups[3], 'earnings' => 12400.00, 'referrals' => 203, 'pending' => 890.25, 'method' => 'paypal', 'paypal' => 'emily@example.com'],
            ['user' => 3, 'code' => 'JAMESK', 'status' => 'pending', 'program' => $mainProgram, 'group' => null, 'earnings' => 0, 'referrals' => 0, 'pending' => 0, 'method' => 'manual'],
            ['user' => 4, 'code' => 'OLIVIA', 'status' => 'active', 'program' => $mainProgram, 'group' => $groups[0], 'earnings' => 560.00, 'referrals' => 15, 'pending' => 75.00, 'method' => 'manual'],
            ['user' => 5, 'code' => 'NOAHB', 'status' => 'suspended', 'program' => $mainProgram, 'group' => null, 'earnings' => 320.00, 'referrals' => 8, 'pending' => 0, 'method' => 'paypal', 'paypal' => 'noah.suspended@example.com'],
            ['user' => 6, 'code' => 'SOPHIAD', 'status' => 'active', 'program' => $premiumProgram, 'group' => $groups[2], 'earnings' => 7800.00, 'referrals' => 156, 'pending' => 425.00, 'method' => 'stripe', 'stripe' => 'acct_0987654321'],
            ['user' => 7, 'code' => 'REJECTED1', 'status' => 'rejected', 'program' => $mainProgram, 'group' => null, 'earnings' => 0, 'referrals' => 0, 'pending' => 0, 'method' => 'manual'],
        ];

        $affiliates = [];
        foreach ($affiliateData as $d) {
            if (!isset($users[$d['user']])) {
                continue;
            }

            $a = new AffiliateElement();
            $a->title = $users[$d['user']]->fullName ?: $users[$d['user']]->username;
            $a->userId = $users[$d['user']]->id;
            $a->programId = $d['program']->id;
            $a->affiliateStatus = $d['status'];
            $a->referralCode = $d['code'];
            $a->groupId = $d['group']?->id;
            $a->payoutMethod = $d['method'];
            $a->paypalEmail = $d['paypal'] ?? null;
            $a->stripeAccountId = $d['stripe'] ?? null;
            $a->lifetimeEarnings = $d['earnings'];
            $a->lifetimeReferrals = $d['referrals'];
            $a->pendingBalance = $d['pending'];
            $a->payoutThreshold = 50.0;
            // @phpstan-ignore-next-line ternary.alwaysTrue (PHPStan narrows $d['status'] from the literal array shape)
            $a->dateApproved = $d['status'] === 'active' ? new \DateTime('-' . rand(30, 365) . ' days') : null;
            $this->saveOrFail($a, 'Affiliate');
            $affiliates[] = $a;
        }

        return $affiliates;
    }

    /**
     * @param list<ProgramElement> $programs
     * @return list<CommissionRuleElement>
     */
    private function seedCommissionRules(array $programs): array
    {
        $this->stdout("  Seeding commission rules...\n");

        $main = $programs[0];
        $premium = $programs[1];

        $data = [
            ['program' => $main, 'name' => 'Standard Product Commission', 'type' => 'product', 'rate' => 15.0, 'priority' => 10],
            ['program' => $main, 'name' => 'Electronics Category Boost', 'type' => 'category', 'rate' => 20.0, 'priority' => 20],
            ['program' => $main, 'name' => 'Tiered: 50+ Referrals', 'type' => 'tiered', 'rate' => 18.0, 'priority' => 30, 'threshold' => 50],
            ['program' => $main, 'name' => 'Tiered: 100+ Referrals', 'type' => 'tiered', 'rate' => 22.0, 'priority' => 31, 'threshold' => 100],
            ['program' => $main, 'name' => 'Spring Bonus 2025', 'type' => 'bonus', 'rate' => 5.0, 'priority' => 50],
            ['program' => $main, 'name' => 'MLM Tier 2', 'type' => 'mlm_tier', 'rate' => 5.0, 'priority' => 5, 'tierLevel' => 2],
            ['program' => $premium, 'name' => 'Premium Base Rate', 'type' => 'product', 'rate' => 25.0, 'priority' => 10],
            ['program' => $premium, 'name' => 'Premium Tiered: 200+', 'type' => 'tiered', 'rate' => 30.0, 'priority' => 30, 'threshold' => 200],
        ];

        $rules = [];
        foreach ($data as $d) {
            $r = new CommissionRuleElement();
            $r->programId = $d['program']->id;
            $r->name = $d['name'];
            $r->type = $d['type'];
            $r->commissionRate = $d['rate'];
            $r->commissionType = 'percentage';
            $r->priority = $d['priority'];
            $r->tierThreshold = $d['threshold'] ?? null;
            $r->tierLevel = $d['tierLevel'] ?? null;
            $this->saveOrFail($r, 'CommissionRule');
            $rules[] = $r;
        }

        return $rules;
    }

    /**
     * @param list<AffiliateElement> $affiliates
     * @param list<ProgramElement> $programs
     * @return list<ClickRecord>
     */
    private function seedClicks(array $affiliates, array $programs): array
    {
        $this->stdout("  Seeding clicks...\n");

        $activeAffiliates = array_filter($affiliates, fn($a) => $a->affiliateStatus === 'active');
        if (empty($activeAffiliates)) {
            return [];
        }
        $activeAffiliates = array_values($activeAffiliates);

        $ips = ['192.168.1.10', '10.0.0.5', '172.16.0.1', '203.0.113.42', '198.51.100.7', '93.184.216.34', '151.101.1.69', '104.21.2.1'];
        $agents = [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
            'Mozilla/5.0 (Linux; Android 14)',
        ];
        $pages = ['/products', '/sale', '/collections/new', '/products/widget-pro', '/bundles', '/'];

        $clicks = [];
        for ($i = 0; $i < 50; $i++) {
            $affiliate = $activeAffiliates[array_rand($activeAffiliates)];
            $daysAgo = rand(0, 90);

            $record = new ClickRecord();
            $record->affiliateId = $affiliate->id;
            $record->programId = $affiliate->programId;
            $record->ip = $ips[array_rand($ips)];
            $record->userAgent = $agents[array_rand($agents)];
            $record->referrerUrl = 'https://blog.example.com/review-' . rand(1, 20);
            $record->landingUrl = 'https://shop.example.com' . $pages[array_rand($pages)] . '?ref=' . $affiliate->referralCode;
            $record->isUnique = rand(0, 3) !== 0; // 75% unique
            $record->dateCreated = (new \DateTime("-{$daysAgo} days -" . rand(0, 23) . " hours"))->format('Y-m-d H:i:s');
            $record->save(false);
            $clicks[] = $record;
        }

        return $clicks;
    }

    /**
     * @param list<AffiliateElement> $affiliates
     * @param list<ProgramElement> $programs
     * @param list<ClickRecord> $clicks
     * @return list<ReferralElement>
     */
    private function seedReferrals(array $affiliates, array $programs, array $clicks): array
    {
        $this->stdout("  Seeding referrals...\n");

        $activeAffiliates = array_values(array_filter($affiliates, fn($a) => $a->affiliateStatus === 'active'));
        if (empty($activeAffiliates)) {
            return [];
        }

        $statuses = ['pending', 'approved', 'approved', 'approved', 'paid', 'paid', 'flagged', 'rejected'];
        $methods = ['cookie', 'cookie', 'cookie', 'coupon', 'direct_link', 'lifetime_customer'];
        $emails = ['alice@example.com', 'bob@example.com', 'carol@example.com', 'dave@example.com', 'eve@example.com', 'frank@example.com', 'grace@example.com', 'hank@example.com', 'iris@example.com', 'jack@example.com'];

        $referrals = [];
        for ($i = 0; $i < 35; $i++) {
            $affiliate = $activeAffiliates[array_rand($activeAffiliates)];
            $daysAgo = rand(1, 60);
            $status = $statuses[array_rand($statuses)];
            $subtotal = round(rand(2500, 45000) / 100, 2); // $25.00 - $450.00

            $r = new ReferralElement();
            $r->affiliateId = $affiliate->id;
            $r->programId = $affiliate->programId;
            $r->orderId = 1000 + $i;
            $r->clickId = !empty($clicks) ? $clicks[array_rand($clicks)]->id : null;
            $r->customerEmail = $emails[array_rand($emails)];
            $r->orderSubtotal = $subtotal;
            $r->referralStatus = $status;
            $r->attributionMethod = $methods[array_rand($methods)];
            $r->couponCode = $r->attributionMethod === 'coupon' ? strtoupper($affiliate->referralCode) : null;

            if ($status === 'flagged') {
                $flags = ['rapid_conversion', 'ip_reuse', 'click_velocity'];
                $r->fraudFlags = json_encode(array_slice($flags, 0, rand(1, 2)));
            }

            if (in_array($status, ['approved', 'paid'])) {
                $r->dateApproved = new \DateTime("-" . max(0, $daysAgo - 2) . " days");
            }
            if ($status === 'paid') {
                $r->datePaid = new \DateTime("-" . max(0, $daysAgo - 5) . " days");
            }

            $this->saveOrFail($r, 'Referral');
            $referrals[] = $r;
        }

        return $referrals;
    }

    /**
     * @param list<ReferralElement> $referrals
     * @param list<AffiliateElement> $affiliates
     * @return list<CommissionElement>
     */
    private function seedCommissions(array $referrals, array $affiliates): array
    {
        $this->stdout("  Seeding commissions...\n");

        $ruleLabels = ['product:widget-pro', 'category:electronics', 'group:gold', 'group:platinum', 'program:main', 'rule:tiered:50+'];
        $commissions = [];

        foreach ($referrals as $referral) {
            $rate = round(rand(500, 3000) / 100, 2); // 5.00% - 30.00%
            $amount = round($referral->orderSubtotal * $rate / 100, 2);

            $statusMap = [
                'pending' => 'pending',
                'approved' => 'approved',
                'paid' => 'paid',
                'flagged' => 'pending',
                'rejected' => 'rejected',
            ];
            $commissionStatus = $statusMap[$referral->referralStatus] ?? 'pending';

            $c = new CommissionElement();
            $c->referralId = $referral->id;
            $c->affiliateId = $referral->affiliateId;
            $c->amount = $amount;
            $c->currency = KickBack::getCommerceCurrency();
            $c->rate = $rate;
            $c->rateType = 'percentage';
            $c->ruleApplied = $ruleLabels[array_rand($ruleLabels)];
            $c->tier = 1;
            $c->commissionStatus = $commissionStatus;

            if (in_array($commissionStatus, ['approved', 'paid'])) {
                $c->dateApproved = $referral->dateApproved;
            }

            $this->saveOrFail($c, 'Commission');
            $commissions[] = $c;
        }

        return $commissions;
    }

    /**
     * @param list<AffiliateElement> $affiliates
     * @return list<PayoutElement>
     */
    private function seedPayouts(array $affiliates): array
    {
        $this->stdout("  Seeding payouts...\n");

        $activeAffiliates = array_values(array_filter($affiliates, fn($a) => $a->affiliateStatus === 'active'));
        if (empty($activeAffiliates)) {
            return [];
        }

        $payoutData = [
            ['status' => 'completed', 'method' => 'paypal', 'txn' => 'PP-' . strtoupper(bin2hex(random_bytes(8)))],
            ['status' => 'completed', 'method' => 'stripe', 'txn' => 'po_' . bin2hex(random_bytes(12))],
            ['status' => 'completed', 'method' => 'paypal', 'txn' => 'PP-' . strtoupper(bin2hex(random_bytes(8)))],
            ['status' => 'pending', 'method' => 'paypal', 'txn' => null],
            ['status' => 'pending', 'method' => 'stripe', 'txn' => null],
            ['status' => 'pending', 'method' => 'manual', 'txn' => null],
            ['status' => 'failed', 'method' => 'paypal', 'txn' => null, 'notes' => 'PayPal account not verified'],
            ['status' => 'processing', 'method' => 'stripe', 'txn' => null],
        ];

        $payouts = [];
        foreach ($payoutData as $d) {
            $affiliate = $activeAffiliates[array_rand($activeAffiliates)];
            $amount = round(rand(5000, 200000) / 100, 2); // $50 - $2000

            $p = new PayoutElement();
            $p->affiliateId = $affiliate->id;
            $p->amount = $amount;
            $p->currency = KickBack::getCommerceCurrency();
            $p->method = $d['method'];
            $p->payoutStatus = $d['status'];
            $p->transactionId = $d['txn'];
            $p->notes = $d['notes'] ?? null;
            $p->processedAt = $d['status'] === 'completed' ? new \DateTime('-' . rand(1, 30) . ' days') : null;
            $this->saveOrFail($p, 'Payout');
            $payouts[] = $p;
        }

        return $payouts;
    }

    /**
     * @param list<AffiliateElement> $affiliates
     */
    private function seedCoupons(array $affiliates): void
    {
        $this->stdout("  Seeding coupons...\n");

        $activeAffiliates = array_values(array_filter($affiliates, fn($a) => $a->affiliateStatus === 'active'));

        foreach ($activeAffiliates as $i => $affiliate) {
            if ($i >= 4) {
                break;
            }

            $record = new CouponRecord();
            $record->affiliateId = $affiliate->id;
            $record->discountId = 1;
            $record->code = strtoupper($affiliate->referralCode) . '10';
            $record->isVanity = $i < 2;
            $record->save(false);
        }
    }

    /**
     * @param list<AffiliateElement> $affiliates
     */
    private function seedCustomerLinks(array $affiliates): void
    {
        $this->stdout("  Seeding customer links...\n");

        $activeAffiliates = array_values(array_filter($affiliates, fn($a) => $a->affiliateStatus === 'active'));
        $emails = ['alice@example.com', 'bob@example.com', 'carol@example.com', 'dave@example.com', 'eve@example.com'];

        foreach ($activeAffiliates as $i => $affiliate) {
            if ($i >= 5) {
                break;
            }

            $record = new CustomerLinkRecord();
            $record->affiliateId = $affiliate->id;
            $record->customerEmail = $emails[$i];
            $record->save(false);
        }
    }

    /**
     * @return User[]
     */
    private function getOrCreateTestUsers(): array
    {
        $testUsers = [
            ['username' => 'sarah.affiliate', 'email' => 'sarah@test.example.com', 'firstName' => 'Sarah', 'lastName' => 'Chen'],
            ['username' => 'mike.affiliate', 'email' => 'mike@test.example.com', 'firstName' => 'Mike', 'lastName' => 'Johnson'],
            ['username' => 'emily.affiliate', 'email' => 'emily@test.example.com', 'firstName' => 'Emily', 'lastName' => 'Williams'],
            ['username' => 'james.affiliate', 'email' => 'james@test.example.com', 'firstName' => 'James', 'lastName' => 'Kim'],
            ['username' => 'olivia.affiliate', 'email' => 'olivia@test.example.com', 'firstName' => 'Olivia', 'lastName' => 'Martinez'],
            ['username' => 'noah.affiliate', 'email' => 'noah@test.example.com', 'firstName' => 'Noah', 'lastName' => 'Brown'],
            ['username' => 'sophia.affiliate', 'email' => 'sophia@test.example.com', 'firstName' => 'Sophia', 'lastName' => 'Davis'],
            ['username' => 'rejected.affiliate', 'email' => 'rejected@test.example.com', 'firstName' => 'Rejected', 'lastName' => 'Test'],
        ];

        $users = [];
        foreach ($testUsers as $data) {
            $user = User::find()->email($data['email'])->one();

            if ($user === null) {
                $user = new User();
                $user->username = $data['username'];
                $user->email = $data['email'];
                $user->firstName = $data['firstName'];
                $user->lastName = $data['lastName'];
                $user->active = true;
                $user->pending = false;

                if (!Craft::$app->getElements()->saveElement($user)) {
                    $this->stderr("  ⚠ Could not create user {$data['email']}: " . implode(', ', $user->getFirstErrors()) . "\n");
                    continue;
                }
            }

            $users[] = $user;
        }

        return $users;
    }

    private function saveOrFail(\craft\base\ElementInterface $element, string $label): void
    {
        if (!Craft::$app->getElements()->saveElement($element)) {
            $errors = implode(', ', $element->getFirstErrors());
            $this->stderr("  ⚠ Failed to save {$label}: {$errors}\n");
        }
    }

    private function clearData(): void
    {
        $db = Craft::$app->getDb();

        // Reverse dependency order: records first, then elements.
        $db->createCommand()->delete('{{%kickback_customer_links}}')->execute();
        $db->createCommand()->delete('{{%kickback_coupons}}')->execute();
        $db->createCommand()->delete('{{%kickback_clicks}}')->execute();

        $elementTypes = [
            CommissionElement::class,
            ReferralElement::class,
            PayoutElement::class,
            CommissionRuleElement::class,
            AffiliateElement::class,
            AffiliateGroupElement::class,
            ProgramElement::class,
        ];

        foreach ($elementTypes as $type) {
            $elements = $type::find()->all();
            foreach ($elements as $element) {
                Craft::$app->getElements()->deleteElement($element);
            }
        }
    }
}
