<?php

declare(strict_types=1);

namespace anvildev\craftkickback;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\AffiliateGroupElement;
use anvildev\craftkickback\elements\CommissionElement;
use anvildev\craftkickback\elements\CommissionRuleElement;
use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\elements\ProgramElement;
use anvildev\craftkickback\elements\ReferralElement;
use anvildev\craftkickback\helpers\DateHelper;
use anvildev\craftkickback\models\Settings;
use anvildev\craftkickback\services\AffiliateGroupService;
use anvildev\craftkickback\services\AffiliateService;
use anvildev\craftkickback\services\approvals\AffiliateApprovalTarget;
use anvildev\craftkickback\services\approvals\CommissionApprovalTarget;
use anvildev\craftkickback\services\approvals\PayoutApprovalTarget;
use anvildev\craftkickback\services\ApprovalService;
use anvildev\craftkickback\services\CommissionRuleService;
use anvildev\craftkickback\services\CommissionService;
use anvildev\craftkickback\services\CouponService;
use anvildev\craftkickback\services\EmailRenderService;
use anvildev\craftkickback\services\FraudService;
use anvildev\craftkickback\services\NotificationService;
use anvildev\craftkickback\services\PayoutGatewayService;
use anvildev\craftkickback\services\PayoutService;
use anvildev\craftkickback\services\ProgramService;
use anvildev\craftkickback\services\ReferralService;
use anvildev\craftkickback\services\ReportingService;
use anvildev\craftkickback\services\TrackingService;
use anvildev\craftkickback\variables\KickbackVariable;
use anvildev\craftkickback\widgets\StatsWidget;
use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;

/**
 * Kickback - Advanced Affiliate & Referral Marketing for Craft Commerce
 *
 * @method static KickBack getInstance()
 * @method Settings getSettings()
 *
 * @property-read AffiliateService $affiliates
 * @property-read AffiliateGroupService $affiliateGroups
 * @property-read ProgramService $programs
 * @property-read TrackingService $tracking
 * @property-read ReferralService $referrals
 * @property-read CommissionService $commissions
 * @property-read CommissionRuleService $commissionRules
 * @property-read CouponService $coupons
 * @property-read FraudService $fraud
 * @property-read PayoutService $payouts
 * @property-read PayoutGatewayService $payoutGateways
 * @property-read NotificationService $notifications
 * @property-read EmailRenderService $emailRender
 * @property-read ReportingService $reporting
 * @property-read ApprovalService $approvals
 *
 * @author anvildev <dev@anvil.xyz>
 * @copyright anvildev
 * @license MIT
 */
class KickBack extends Plugin
{
    public const PERMISSION_MANAGE_AFFILIATES = 'kickback-manageAffiliates';
    public const PERMISSION_APPROVE_AFFILIATES = 'kickback-approveAffiliates';
    public const PERMISSION_MANAGE_REFERRALS = 'kickback-manageReferrals';
    public const PERMISSION_APPROVE_REFERRALS = 'kickback-approveReferrals';
    public const PERMISSION_MANAGE_COMMISSIONS = 'kickback-manageCommissions';
    public const PERMISSION_APPROVE_COMMISSIONS = 'kickback-approveCommissions';
    public const PERMISSION_MANAGE_PAYOUTS = 'kickback-managePayouts';
    public const PERMISSION_PROCESS_PAYOUTS = 'kickback-processPayouts';
    public const PERMISSION_VERIFY_PAYOUTS = 'kickback-verifyPayouts';
    public const PERMISSION_MANAGE_PROGRAMS = 'kickback-managePrograms';
    public const PERMISSION_VIEW_REPORTS = 'kickback-viewReports';
    public const PERMISSION_MANAGE_SETTINGS = 'kickback-manageSettings';

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'components' => [
                'affiliates' => AffiliateService::class,
                'affiliateGroups' => AffiliateGroupService::class,
                'programs' => ProgramService::class,
                'tracking' => TrackingService::class,
                'referrals' => ReferralService::class,
                'commissions' => CommissionService::class,
                'commissionRules' => CommissionRuleService::class,
                'coupons' => CouponService::class,
                'fraud' => FraudService::class,
                'payouts' => PayoutService::class,
                'payoutGateways' => PayoutGatewayService::class,
                'notifications' => NotificationService::class,
                'emailRender' => EmailRenderService::class,
                'reporting' => ReportingService::class,
                'approvals' => ApprovalService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::setAlias('@kickback', $this->getBasePath());

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'anvildev\\craftkickback\\console\\controllers';
        }

        $this->registerSiteTemplateRoots();
        $this->registerElements();
        $this->registerWidgets();
        $this->registerVariable();
        $this->registerCpRoutes();
        $this->registerSiteRoutes();
        $this->registerPermissions();
        $this->registerGarbageCollection();
        $this->registerCommerceListeners();
        $this->registerNotificationListeners();
        $this->registerGqlTypes();
        $this->registerGqlQueries();
        $this->registerGqlSchemaComponents();

        $this->approvals->registerTarget('payout', PayoutApprovalTarget::class);
        $this->approvals->registerTarget('affiliate', AffiliateApprovalTarget::class);
        $this->approvals->registerTarget('commission', CommissionApprovalTarget::class);

        Craft::$app->onInit(function() {
            $this->handleSiteReferralParam();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = 'Kickback';
        $user = Craft::$app->getUser();

        $subnav = ['dashboard' => ['label' => Craft::t('kickback', 'nav.dashboard'), 'url' => 'kickback']];

        foreach ([
            ['affiliates', 'Affiliates', 'kickback/affiliates', self::PERMISSION_MANAGE_AFFILIATES],
            ['referrals', 'Referrals', 'kickback/referrals', self::PERMISSION_MANAGE_REFERRALS],
            ['fraud', 'Fraud Detection', 'kickback/fraud', self::PERMISSION_MANAGE_REFERRALS],
            ['commissions', 'Commissions', 'kickback/commissions', self::PERMISSION_MANAGE_COMMISSIONS],
            ['commission-rules', 'Commission Rules', 'kickback/commission-rules', self::PERMISSION_MANAGE_COMMISSIONS],
            ['affiliate-groups', 'Affiliate Groups', 'kickback/affiliate-groups', self::PERMISSION_MANAGE_AFFILIATES],
            ['payouts', 'Payouts', 'kickback/payouts', self::PERMISSION_MANAGE_PAYOUTS],
            ['approvals', 'Verifications', 'kickback/approvals', self::PERMISSION_VERIFY_PAYOUTS],
            ['programs', 'Programs', 'kickback/programs', self::PERMISSION_MANAGE_PROGRAMS],
            ['reports', 'Reports', 'kickback/reports', self::PERMISSION_VIEW_REPORTS],
            ['settings', 'Settings', 'kickback/settings', self::PERMISSION_MANAGE_SETTINGS],
        ] as [$key, $label, $url, $permission]) {
            if ($user->checkPermission($permission)) {
                $subnav[$key] = ['label' => Craft::t('kickback', $label), 'url' => $url];
            }
        }

        foreach ([
            [self::PERMISSION_APPROVE_REFERRALS, ['referrals' => 'Referrals', 'fraud' => 'Fraud Detection']],
            [self::PERMISSION_APPROVE_COMMISSIONS, ['commissions' => 'Commissions']],
        ] as [$perm, $items]) {
            if ($user->checkPermission($perm)) {
                foreach ($items as $key => $label) {
                    $subnav[$key] ??= ['label' => Craft::t('kickback', $label), 'url' => "kickback/{$key}"];
                }
            }
        }

        $item['subnav'] = $subnav;
        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('kickback/settings/index', [
            'settings' => $this->getSettings(),
        ]);
    }

    /**
     * Primary payment currency ISO code, or 'USD' if Commerce isn't installed.
     */
    public static function getCommerceCurrency(): string
    {
        if (class_exists(\craft\commerce\Plugin::class)) {
            return \craft\commerce\Plugin::getInstance()
                ->getPaymentCurrencies()
                ->getPrimaryPaymentCurrencyIso();
        }

        return 'USD';
    }

    public function afterInstall(): void
    {
        parent::afterInstall();

        $this->programs->createDefaultProgram();
        $this->seedPrimarySitePortalConfig();
    }

    /**
     * Enable the affiliate portal on the primary site at `/affiliate` so a
     * fresh install has a working portal without manual configuration. Only
     * writes when both portal settings are empty - respects any config already
     * set via `config/kickback.php`.
     */
    private function seedPrimarySitePortalConfig(): void
    {
        $settings = $this->getSettings();
        if (!$settings instanceof Settings) {
            return;
        }

        if ($settings->affiliatePortalEnabledSites !== [] || $settings->affiliatePortalPaths !== []) {
            return;
        }

        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $settings->affiliatePortalEnabledSites = [$primarySite->handle => true];
        $settings->affiliatePortalPaths = [$primarySite->handle => 'affiliate'];

        Craft::$app->getPlugins()->savePluginSettings($this, $settings->toArray());
    }

    private function registerSiteTemplateRoots(): void
    {
        Event::on(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $event) {
            $dir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
            if (is_dir($dir)) {
                $event->roots[$this->id] = $dir;
            }
        });
    }

    private function registerElements(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            array_push($event->types, AffiliateElement::class, AffiliateGroupElement::class,
                ProgramElement::class, CommissionRuleElement::class, PayoutElement::class,
                ReferralElement::class, CommissionElement::class);
        });
    }

    private function registerWidgets(): void
    {
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = StatsWidget::class;
        });
    }

    private function registerVariable(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('kickback', KickbackVariable::class);
        });
    }

    private function registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules += [
                'kickback' => 'kickback/dashboard/index',
                'kickback/dashboard' => 'kickback/dashboard/index',
                'kickback/settings' => 'kickback/settings/index',
                'kickback/approvals' => 'kickback/approvals/index',
                'kickback/approvals/approve' => 'kickback/approvals/approve',
                'kickback/approvals/reject' => 'kickback/approvals/reject',
                'kickback/fraud' => 'kickback/fraud/index',
                'kickback/fraud/export' => 'kickback/fraud/export',
                'kickback/payouts' => 'kickback/payouts/index',
                'kickback/payouts/batch' => 'kickback/payouts/batch',
                'kickback/payouts/export' => 'kickback/payouts/export',
                'kickback/payouts/<payoutId:\d+>' => 'kickback/payouts/view',
                'kickback/reports' => 'kickback/reports/index',
                'kickback/reports/export' => 'kickback/reports/export',
            ];

            foreach ([
                'referrals' => '<referralId:\d+>',
                'commissions' => '<commissionId:\d+>',
            ] as $section => $param) {
                $event->rules += [
                    "kickback/{$section}" => "kickback/{$section}/index",
                    "kickback/{$section}/export" => "kickback/{$section}/export",
                    "kickback/{$section}/{$param}" => "kickback/{$section}/edit",
                ];
            }

            $event->rules += [
                'kickback/affiliates' => 'kickback/affiliates/index',
                'kickback/affiliates/export' => 'kickback/affiliates/export',
                'kickback/affiliates/new' => 'kickback/affiliates/edit',
                'kickback/affiliates/<affiliateId:\d+>' => 'kickback/affiliates/edit',
            ];

            foreach ([
                'commission-rules' => '<ruleId:\d+>',
                'affiliate-groups' => '<groupId:\d+>',
                'programs' => '<programId:\d+>',
            ] as $section => $param) {
                $event->rules += [
                    "kickback/{$section}" => "kickback/{$section}/index",
                    "kickback/{$section}/export" => "kickback/{$section}/export",
                    "kickback/{$section}/new" => "kickback/{$section}/edit",
                    "kickback/{$section}/{$param}" => "kickback/{$section}/edit",
                ];
            }
        });
    }

    private function registerPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $t = static fn(string $msg) => Craft::t('kickback', $msg);
            $event->permissions[] = [
                'heading' => $t('Kickback'),
                'permissions' => [
                    self::PERMISSION_MANAGE_AFFILIATES => ['label' => $t('Manage affiliates'), 'nested' => [
                        self::PERMISSION_APPROVE_AFFILIATES => ['label' => $t('Approve/reject affiliates')],
                    ]],
                    self::PERMISSION_MANAGE_REFERRALS => ['label' => $t('Manage referrals'), 'nested' => [
                        self::PERMISSION_APPROVE_REFERRALS => ['label' => $t('Approve/reject referrals')],
                    ]],
                    self::PERMISSION_MANAGE_COMMISSIONS => ['label' => $t('Manage commissions'), 'nested' => [
                        self::PERMISSION_APPROVE_COMMISSIONS => ['label' => $t('Approve/reject/reverse commissions')],
                    ]],
                    self::PERMISSION_MANAGE_PAYOUTS => ['label' => $t('Manage payouts'), 'nested' => [
                        self::PERMISSION_PROCESS_PAYOUTS => ['label' => $t('Process payouts')],
                        self::PERMISSION_VERIFY_PAYOUTS => ['label' => $t('Verify payouts')],
                    ]],
                    self::PERMISSION_MANAGE_PROGRAMS => ['label' => $t('Manage programs')],
                    self::PERMISSION_VIEW_REPORTS => ['label' => $t('View reports')],
                    self::PERMISSION_MANAGE_SETTINGS => ['label' => $t('Manage settings')],
                ],
            ];
        });
    }

    private function registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['r/<code:[a-zA-Z0-9_-]+>'] = 'kickback/track/track';
            // Webhook receiver - public, no CSRF. Each gateway enforces its own signature check.
            $event->rules['kickback/webhooks/<handle:[a-z][a-z0-9_-]*>'] = 'kickback/webhooks/handle';

            if (($p = $this->getSettings()->getCurrentSitePortalPath()) === null) {
                return;
            }
            foreach (['links', 'referrals', 'commissions', 'coupons', 'team', 'settings', 'stripe-onboard', 'pending'] as $sub) {
                $event->rules["{$p}/{$sub}"] = "kickback/portal/{$sub}";
            }
            $event->rules[$p] = 'kickback/portal/dashboard';
            $event->rules["{$p}/register"] = 'kickback/registration/form';
        });
    }

    private function registerCommerceListeners(): void
    {
        if (!class_exists(Order::class)) {
            return;
        }

        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, function(Event $event) {
            $this->referrals->processOrder($event->sender);
        });

        if (class_exists(\craft\commerce\services\Payments::class)) {
            Event::on(\craft\commerce\services\Payments::class,
                \craft\commerce\services\Payments::EVENT_AFTER_REFUND_TRANSACTION,
                fn(\craft\commerce\events\RefundTransactionEvent $e) => $this->referrals->handleRefund($e->transaction));
        }

        if (class_exists(\craft\commerce\services\OrderHistories::class)) {
            Event::on(\craft\commerce\services\OrderHistories::class,
                \craft\commerce\services\OrderHistories::EVENT_ORDER_STATUS_CHANGE,
                fn(\craft\commerce\events\OrderStatusEvent $e) => $this->referrals->handleOrderStatusChange($e->order));
        }
    }

    /**
     * Site-request ?ref= handler. Validates, looks up the affiliate, and
     * records the click unless a matching cookie is already present.
     */
    private function handleSiteReferralParam(): void
    {
        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest() || $request->getIsCpRequest() || !$request->getIsGet()) {
            return;
        }

        $refCode = $request->getQueryParam($this->getSettings()->referralParamName);
        if (!is_string($refCode) || $refCode === '' || strlen($refCode) > 64
            || preg_match('/^[a-zA-Z0-9_-]+$/', $refCode) !== 1) {
            return;
        }

        $affiliate = $this->affiliates->getAffiliateByReferralCode($refCode);
        if ($affiliate === null || $affiliate->affiliateStatus !== AffiliateElement::STATUS_ACTIVE) {
            return;
        }

        $cookie = $this->tracking->getReferralCookie();
        if ($cookie !== null && $cookie['code'] === $refCode) {
            return;
        }

        $this->tracking->recordClick($affiliate, $this->sanitizeLandingUrl(
            $request->getAbsoluteUrl(),
            '/' . ltrim($request->getPathInfo(), '/'),
        ));
    }

    /**
     * Reject non-HTTP(S), malformed, or oversized URLs.
     */
    private function sanitizeLandingUrl(string $url, string $fallbackPath): string
    {
        if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $fallbackPath;
        }
        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : $fallbackPath;
    }

    private function registerNotificationListeners(): void
    {
        foreach ([
            [AffiliateService::class, AffiliateService::EVENT_AFTER_APPROVE_AFFILIATE, 'onAffiliateApproved'],
            [AffiliateService::class, AffiliateService::EVENT_AFTER_REJECT_AFFILIATE, 'onAffiliateRejected'],
            [PayoutService::class, PayoutService::EVENT_AFTER_PROCESS_PAYOUT, 'onPayoutCompleted'],
            [FraudService::class, FraudService::EVENT_AFTER_FLAG_REFERRAL, 'onReferralFlagged'],
        ] as [$class, $event, $method]) {
            Event::on($class, $event, fn($e) => $this->notifications->$method($e));
        }
    }

    private function registerGqlTypes(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
            array_push($event->types,
                \anvildev\craftkickback\gql\interfaces\elements\AffiliateInterface::class,
                \anvildev\craftkickback\gql\interfaces\elements\ProgramInterface::class,
                \anvildev\craftkickback\gql\interfaces\elements\ReferralInterface::class,
                \anvildev\craftkickback\gql\interfaces\elements\CommissionInterface::class,
                \anvildev\craftkickback\gql\interfaces\elements\PayoutInterface::class,
                \anvildev\craftkickback\gql\interfaces\elements\AffiliateGroupInterface::class,
                \anvildev\craftkickback\gql\interfaces\elements\CommissionRuleInterface::class);
        });
    }

    private function registerGqlQueries(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_QUERIES, function(RegisterGqlQueriesEvent $event) {
            $event->queries = array_merge($event->queries, \anvildev\craftkickback\gql\queries\KickbackQuery::getQueries());
        });
    }

    /**
     * Register per-schema scope handles so admins can grant access in
     * CP -> GraphQL -> Schemas. Resolver-level isPublicSchema() guard for defence in depth.
     */
    private function registerGqlSchemaComponents(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, function(RegisterGqlSchemaComponentsEvent $event) {
            $scopes = [];
            foreach (['affiliates', 'affiliateGroups', 'programs', 'commissionRules', 'referrals', 'commissions', 'payouts'] as $type) {
                $scopes["kickback.{$type}:read"] = ['label' => Craft::t('kickback', 'Query ' . strtolower(preg_replace('/([A-Z])/', ' $1', $type)))];
            }
            $event->queries[Craft::t('kickback', 'plugin.name')] = $scopes;
        });
    }

    private function registerGarbageCollection(): void
    {
        Event::on(Gc::class, Gc::EVENT_RUN, function() {
            $gc = Craft::$app->getGc();
            foreach ([
                AffiliateElement::class => '{{%kickback_affiliates}}',
                AffiliateGroupElement::class => '{{%kickback_affiliate_groups}}',
                ProgramElement::class => '{{%kickback_programs}}',
                CommissionRuleElement::class => '{{%kickback_commission_rules}}',
                PayoutElement::class => '{{%kickback_payouts}}',
                ReferralElement::class => '{{%kickback_referrals}}',
                CommissionElement::class => '{{%kickback_commissions}}',
            ] as $class => $table) {
                $gc->deletePartialElements($class, $table, 'id');
            }

            $settings = $this->getSettings();
            if ($settings->autoApproveReferrals && $settings->holdPeriodDays > 0) {
                Craft::$app->getQueue()->push(new \anvildev\craftkickback\jobs\ApproveHeldReferralsJob());
            }

            $clickRetention = $settings->clickRetentionDays;
            if ($clickRetention > 0) {
                $cutoff = DateHelper::pastCutoffString("-{$clickRetention} days");
                Craft::$app->getDb()->createCommand()
                    ->delete('{{%kickback_clicks}}', ['<', 'dateCreated', $cutoff])
                    ->execute();
            }
        });
    }
}
