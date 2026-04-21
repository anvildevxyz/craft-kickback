<?php

declare(strict_types=1);

namespace anvildev\craftkickback\migrations;

use craft\db\Migration;

/**
 * Installation migration that creates all Kickback database tables, indexes, and foreign keys.
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    public function safeDown(): bool
    {
        // Drop in reverse dependency order. The *_sites join tables must drop
        // before their parent since they carry FKs back to it.
        $this->dropTableIfExists('{{%kickback_customer_links}}');
        $this->dropTableIfExists('{{%kickback_coupons}}');
        $this->dropTableIfExists('{{%kickback_commissions}}');
        $this->dropTableIfExists('{{%kickback_approvals}}');
        $this->dropTableIfExists('{{%kickback_payouts}}');
        $this->dropTableIfExists('{{%kickback_referrals}}');
        $this->dropTableIfExists('{{%kickback_clicks}}');
        $this->dropTableIfExists('{{%kickback_affiliates}}');
        $this->dropTableIfExists('{{%kickback_commission_rules}}');
        $this->dropTableIfExists('{{%kickback_affiliate_groups}}');
        $this->dropTableIfExists('{{%kickback_programs_sites}}');
        $this->dropTableIfExists('{{%kickback_programs}}');

        return true;
    }

    private function createTables(): void
    {
        // Programs (element-backed). Translatable fields (name, description,
        // termsAndConditions) live in the kickback_programs_sites join table.
        $this->createTable('{{%kickback_programs}}', [
            'id' => $this->integer()->notNull(),
            'handle' => $this->string(64)->notNull(),
            'defaultCommissionRate' => $this->decimal(8, 4)->notNull()->defaultValue(10),
            'defaultCommissionType' => $this->string(20)->notNull()->defaultValue('percentage'),
            'cookieDuration' => $this->integer()->notNull()->defaultValue(30),
            'allowSelfReferral' => $this->boolean()->notNull()->defaultValue(false),
            'enableCouponCreation' => $this->boolean()->notNull()->defaultValue(true),
            'propagationMethod' => $this->string(50)->notNull()->defaultValue('none'),
            'status' => $this->string(20)->notNull()->defaultValue('active'),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Per-site translatable fields for programs.
        $this->createTable('{{%kickback_programs_sites}}', [
            'id' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text(),
            'termsAndConditions' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]], [[siteId]])',
        ]);

        // Affiliate groups (element-backed)
        $this->createTable('{{%kickback_affiliate_groups}}', [
            'id' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'handle' => $this->string(64)->notNull(),
            'commissionRate' => $this->decimal(8, 4)->notNull(),
            'commissionType' => $this->string(20)->notNull()->defaultValue('percentage'),
            'sortOrder' => $this->integer()->notNull()->defaultValue(0),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Affiliates (element-backed)
        $this->createTable('{{%kickback_affiliates}}', [
            'id' => $this->integer()->notNull(),
            'userId' => $this->integer()->notNull(),
            'programId' => $this->integer()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'referralCode' => $this->string(50)->notNull(),
            'commissionRateOverride' => $this->decimal(8, 4),
            'commissionTypeOverride' => $this->string(20),
            'parentAffiliateId' => $this->integer(),
            'tierLevel' => $this->integer()->notNull()->defaultValue(1),
            'groupId' => $this->integer(),
            'paypalEmail' => $this->string(255),
            'stripeAccountId' => $this->string(255),
            'payoutMethod' => $this->string(20)->notNull()->defaultValue('manual'),
            'payoutThreshold' => $this->decimal(14, 4)->notNull()->defaultValue(50),
            'lifetimeEarnings' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'lifetimeReferrals' => $this->integer()->notNull()->defaultValue(0),
            'pendingBalance' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'notes' => $this->text(),
            'dateApproved' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Commission rules (element-backed)
        $this->createTable('{{%kickback_commission_rules}}', [
            'id' => $this->integer()->notNull(),
            'programId' => $this->integer()->notNull(),
            'name' => $this->string(255)->notNull(),
            'type' => $this->string(20)->notNull(),
            'targetId' => $this->integer(),
            'commissionRate' => $this->decimal(8, 4)->notNull(),
            'commissionType' => $this->string(20)->notNull()->defaultValue('percentage'),
            'tierThreshold' => $this->integer(),
            'tierLevel' => $this->integer(),
            'lookbackDays' => $this->integer(),
            'priority' => $this->integer()->notNull()->defaultValue(0),
            'conditions' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Clicks (high volume)
        $this->createTable('{{%kickback_clicks}}', [
            'id' => $this->primaryKey(),
            'affiliateId' => $this->integer()->notNull(),
            'programId' => $this->integer()->notNull(),
            'ip' => $this->string(45)->notNull(),
            'userAgent' => $this->string(500),
            'referrerUrl' => $this->string(2048),
            'landingUrl' => $this->string(2048)->notNull(),
            'subId' => $this->string(255),
            'isUnique' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
        ]);

        // Referrals (element-backed)
        $this->createTable('{{%kickback_referrals}}', [
            'id' => $this->integer()->notNull(),
            'affiliateId' => $this->integer(),
            'programId' => $this->integer()->notNull(),
            'orderId' => $this->integer(),
            'clickId' => $this->integer(),
            'customerId' => $this->integer(),
            'customerEmail' => $this->string(255),
            'orderSubtotal' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'attributionMethod' => $this->string(20)->notNull()->defaultValue('cookie'),
            'couponCode' => $this->string(64),
            'subId' => $this->string(255),
            'referralResolutionTrace' => $this->text(),
            'fraudFlags' => $this->text(),
            'dateApproved' => $this->dateTime(),
            'datePaid' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Commissions (element-backed).
        //
        // originalAmount is an immutable snapshot of the commission's initial
        // value, set once at creation and never updated. amount tracks the
        // current value after any partial-refund reductions. Storing both lets
        // reverseCommissionsProportionally recompute amount = originalAmount *
        // (1 - cumulativeRefundRatio) on each refund event without compounding.
        $this->createTable('{{%kickback_commissions}}', [
            'id' => $this->integer()->notNull(),
            'referralId' => $this->integer(),
            'affiliateId' => $this->integer(),
            'amount' => $this->decimal(14, 4)->notNull(),
            'originalAmount' => $this->decimal(14, 4)->notNull()->defaultValue(0),
            'currency' => $this->string(3)->notNull()->defaultValue('USD'),
            'rate' => $this->decimal(8, 4)->notNull(),
            'rateType' => $this->string(20)->notNull(),
            'ruleApplied' => $this->string(255),
            'ruleResolutionTrace' => $this->text(),
            'tier' => $this->integer()->notNull()->defaultValue(1),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'payoutId' => $this->integer(),
            'description' => $this->string(255),
            'dateApproved' => $this->dateTime(),
            'dateReversed' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Payouts (element-backed)
        $this->createTable('{{%kickback_payouts}}', [
            'id' => $this->integer()->notNull(),
            'affiliateId' => $this->integer(),
            'createdByUserId' => $this->integer()->null(),
            'amount' => $this->decimal(14, 4)->notNull(),
            'currency' => $this->string(3)->notNull()->defaultValue('USD'),
            'method' => $this->string(20)->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'transactionId' => $this->string(255),
            'gatewayBatchId' => $this->string(255),
            'notes' => $this->text(),
            'processedAt' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY([[id]])',
        ]);

        // Approvals (polymorphic).
        $this->createTable('{{%kickback_approvals}}', [
            'id' => $this->primaryKey(),
            'targetType' => $this->string(40)->notNull(),
            'targetId' => $this->integer()->notNull(),
            'status' => $this->string(20)->notNull()->defaultValue('pending'),
            'requestedUserId' => $this->integer()->null(),
            'resolvedUserId' => $this->integer()->null(),
            'resolvedAt' => $this->dateTime()->null(),
            'note' => $this->text()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Coupons (affiliate-to-discount mapping)
        $this->createTable('{{%kickback_coupons}}', [
            'id' => $this->primaryKey(),
            'affiliateId' => $this->integer()->notNull(),
            'discountId' => $this->integer()->notNull(),
            'code' => $this->string(64)->notNull(),
            'isVanity' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Customer links (lifetime commission tracking)
        $this->createTable('{{%kickback_customer_links}}', [
            'id' => $this->primaryKey(),
            'affiliateId' => $this->integer()->notNull(),
            'customerEmail' => $this->string(255)->notNull(),
            'customerId' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    private function createIndexes(): void
    {
        // Programs
        $this->createIndex(null, '{{%kickback_programs}}', ['handle'], true);
        $this->createIndex(null, '{{%kickback_programs}}', ['status']);
        $this->createIndex(null, '{{%kickback_programs_sites}}', ['siteId']);

        // Affiliate groups
        $this->createIndex(null, '{{%kickback_affiliate_groups}}', ['handle'], true);

        // Affiliates
        $this->createIndex(null, '{{%kickback_affiliates}}', ['userId']);
        $this->createIndex(null, '{{%kickback_affiliates}}', ['programId']);
        $this->createIndex(null, '{{%kickback_affiliates}}', ['referralCode'], true);
        $this->createIndex(null, '{{%kickback_affiliates}}', ['status']);
        $this->createIndex(null, '{{%kickback_affiliates}}', ['groupId']);
        $this->createIndex(null, '{{%kickback_affiliates}}', ['parentAffiliateId']);

        // Commission rules
        $this->createIndex(null, '{{%kickback_commission_rules}}', ['programId']);
        $this->createIndex(null, '{{%kickback_commission_rules}}', ['type']);
        $this->createIndex(null, '{{%kickback_commission_rules}}', ['priority']);

        // Clicks
        $this->createIndex(null, '{{%kickback_clicks}}', ['affiliateId']);
        $this->createIndex(null, '{{%kickback_clicks}}', ['programId']);
        $this->createIndex(null, '{{%kickback_clicks}}', ['ip']);
        $this->createIndex(null, '{{%kickback_clicks}}', ['dateCreated']);
        $this->createIndex(null, '{{%kickback_clicks}}', ['ip', 'dateCreated']);
        $this->createIndex(null, '{{%kickback_clicks}}', ['ip', 'affiliateId', 'dateCreated']);

        // Referrals
        $this->createIndex(null, '{{%kickback_referrals}}', ['affiliateId']);
        $this->createIndex(null, '{{%kickback_referrals}}', ['programId']);
        $this->createIndex(null, '{{%kickback_referrals}}', ['orderId']);
        $this->createIndex(null, '{{%kickback_referrals}}', ['status']);
        $this->createIndex(null, '{{%kickback_referrals}}', ['customerEmail']);
        $this->createIndex(null, '{{%kickback_referrals}}', ['customerId']);
        $this->createIndex(null, '{{%kickback_referrals}}', ['couponCode']);
        $this->createIndex(null, '{{%kickback_referrals}}', ['dateCreated']);
        $this->createIndex(null, '{{%kickback_referrals}}', ['affiliateId', 'dateCreated']);
        $this->createIndex(null, '{{%kickback_referrals}}', ['affiliateId', 'subId']);

        // Commissions
        $this->createIndex(null, '{{%kickback_commissions}}', ['referralId']);
        $this->createIndex(null, '{{%kickback_commissions}}', ['affiliateId']);
        $this->createIndex(null, '{{%kickback_commissions}}', ['status']);
        $this->createIndex(null, '{{%kickback_commissions}}', ['payoutId']);

        // Payouts
        $this->createIndex(null, '{{%kickback_payouts}}', ['affiliateId']);
        $this->createIndex(null, '{{%kickback_payouts}}', ['status']);
        $this->createIndex(null, '{{%kickback_payouts}}', ['createdByUserId']);

        // Approvals. Unique index is terminal - one approval per (targetType, targetId);
        // re-requests would need to reopen the same row or delete it first.
        $this->createIndex(null, '{{%kickback_approvals}}', ['targetType', 'targetId'], true);
        $this->createIndex(null, '{{%kickback_approvals}}', ['status', 'targetType']);
        $this->createIndex(null, '{{%kickback_approvals}}', ['requestedUserId']);
        $this->createIndex(null, '{{%kickback_approvals}}', ['resolvedUserId']);

        // Coupons
        $this->createIndex(null, '{{%kickback_coupons}}', ['affiliateId']);
        $this->createIndex(null, '{{%kickback_coupons}}', ['code'], true);

        // Customer links
        $this->createIndex(null, '{{%kickback_customer_links}}', ['affiliateId']);
        $this->createIndex(null, '{{%kickback_customer_links}}', ['customerEmail']);
        $this->createIndex(null, '{{%kickback_customer_links}}', ['customerId']);
    }

    private function addForeignKeys(): void
    {
        // Programs → elements
        $this->addForeignKey(null, '{{%kickback_programs}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', 'CASCADE');
        // Programs sites → programs + sites
        $this->addForeignKey(null, '{{%kickback_programs_sites}}', ['id'], '{{%kickback_programs}}', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%kickback_programs_sites}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        // Commission rules → elements
        $this->addForeignKey(null, '{{%kickback_commission_rules}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', 'CASCADE');
        // Payouts → elements
        $this->addForeignKey(null, '{{%kickback_payouts}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', 'CASCADE');
        // Referrals → elements
        $this->addForeignKey(null, '{{%kickback_referrals}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', 'CASCADE');
        // Commissions → elements
        $this->addForeignKey(null, '{{%kickback_commissions}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', 'CASCADE');
        // Affiliate groups → elements
        $this->addForeignKey(null, '{{%kickback_affiliate_groups}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', 'CASCADE');
        // Affiliates → elements
        $this->addForeignKey(null, '{{%kickback_affiliates}}', ['id'], '{{%elements}}', ['id'], 'CASCADE', 'CASCADE');
        // Affiliates → users
        $this->addForeignKey(null, '{{%kickback_affiliates}}', ['userId'], '{{%users}}', ['id'], 'CASCADE', 'CASCADE');
        // Affiliates → programs
        $this->addForeignKey(null, '{{%kickback_affiliates}}', ['programId'], '{{%kickback_programs}}', ['id'], 'CASCADE', 'CASCADE');
        // Affiliates → groups
        $this->addForeignKey(null, '{{%kickback_affiliates}}', ['groupId'], '{{%kickback_affiliate_groups}}', ['id'], 'SET NULL', 'CASCADE');
        // Affiliates → parent affiliate (self-referential)
        $this->addForeignKey(null, '{{%kickback_affiliates}}', ['parentAffiliateId'], '{{%kickback_affiliates}}', ['id'], 'SET NULL', 'CASCADE');

        // Commission rules → programs
        $this->addForeignKey(null, '{{%kickback_commission_rules}}', ['programId'], '{{%kickback_programs}}', ['id'], 'CASCADE', 'CASCADE');

        // Clicks → affiliates
        $this->addForeignKey(null, '{{%kickback_clicks}}', ['affiliateId'], '{{%kickback_affiliates}}', ['id'], 'CASCADE', 'CASCADE');
        // Clicks → programs
        $this->addForeignKey(null, '{{%kickback_clicks}}', ['programId'], '{{%kickback_programs}}', ['id'], 'CASCADE', 'CASCADE');

        // Referrals → affiliates (SET NULL to preserve financial records)
        $this->addForeignKey(null, '{{%kickback_referrals}}', ['affiliateId'], '{{%kickback_affiliates}}', ['id'], 'SET NULL', 'CASCADE');
        // Referrals → programs
        $this->addForeignKey(null, '{{%kickback_referrals}}', ['programId'], '{{%kickback_programs}}', ['id'], 'CASCADE', 'CASCADE');
        // Referrals → clicks
        $this->addForeignKey(null, '{{%kickback_referrals}}', ['clickId'], '{{%kickback_clicks}}', ['id'], 'SET NULL', 'CASCADE');
        // Referrals → customers
        $this->addForeignKey(null, '{{%kickback_referrals}}', ['customerId'], '{{%users}}', ['id'], 'SET NULL', 'CASCADE');

        // Commissions → referrals (SET NULL to preserve financial records)
        $this->addForeignKey(null, '{{%kickback_commissions}}', ['referralId'], '{{%kickback_referrals}}', ['id'], 'SET NULL', 'CASCADE');
        // Commissions → affiliates (SET NULL to preserve financial records)
        $this->addForeignKey(null, '{{%kickback_commissions}}', ['affiliateId'], '{{%kickback_affiliates}}', ['id'], 'SET NULL', 'CASCADE');
        // Commissions → payouts
        $this->addForeignKey(null, '{{%kickback_commissions}}', ['payoutId'], '{{%kickback_payouts}}', ['id'], 'SET NULL', 'CASCADE');

        // Payouts → affiliates (SET NULL to preserve financial records)
        $this->addForeignKey(null, '{{%kickback_payouts}}', ['affiliateId'], '{{%kickback_affiliates}}', ['id'], 'SET NULL', 'CASCADE');
        $this->addForeignKey(null, '{{%kickback_payouts}}', ['createdByUserId'], '{{%users}}', ['id'], 'SET NULL', 'CASCADE');

        // Approvals → users. SET NULL on user delete so approval history survives
        // when the requester or resolver is removed. No FK on targetId - polymorphic;
        // ApprovalService::deleteFor() cascades from the target's afterDelete().
        $this->addForeignKey(null, '{{%kickback_approvals}}', ['requestedUserId'], '{{%users}}', ['id'], 'SET NULL', 'CASCADE');
        $this->addForeignKey(null, '{{%kickback_approvals}}', ['resolvedUserId'], '{{%users}}', ['id'], 'SET NULL', 'CASCADE');

        // Coupons → affiliates
        $this->addForeignKey(null, '{{%kickback_coupons}}', ['affiliateId'], '{{%kickback_affiliates}}', ['id'], 'CASCADE', 'CASCADE');
        // Coupons → Commerce discounts (only when Commerce is installed)
        if ($this->db->tableExists('{{%commerce_discounts}}')) {
            $this->addForeignKey(null, '{{%kickback_coupons}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        }

        // Customer links → affiliates
        $this->addForeignKey(null, '{{%kickback_customer_links}}', ['affiliateId'], '{{%kickback_affiliates}}', ['id'], 'CASCADE', 'CASCADE');
    }
}
