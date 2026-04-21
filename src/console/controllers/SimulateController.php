<?php

declare(strict_types=1);

namespace anvildev\craftkickback\console\controllers;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\CommissionElement;
use anvildev\craftkickback\elements\CommissionRuleElement;
use anvildev\craftkickback\elements\ReferralElement;
use anvildev\craftkickback\helpers\SimulationHelper;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Simulate high-volume referral/commission traffic for rule validation.
 */
class SimulateController extends Controller
{
    public int $count = 200;
    public int $seed = 42;
    public bool $dryRun = true;
    public ?string $report = null;
    public string $mix = 'product:25,category:20,tiered:20,bonus:15,group:10,program:10';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'count',
            'seed',
            'dryRun',
            'report',
            'mix',
        ]);
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            $this->stderr("kickback/simulate/run refuses to run outside devMode.\n");
            return false;
        }

        if ($this->count < 1) {
            $this->stderr("--count must be >= 1\n");
            return false;
        }

        return true;
    }

    public function actionRun(): int
    {
        mt_srand($this->seed);

        $plugin = KickBack::getInstance();
        $weights = SimulationHelper::parseWeightedMix($this->mix);
        if ($weights === []) {
            $this->stderr("Invalid --mix value. Example: product:25,category:20,tiered:20,bonus:15,group:10,program:10\n");
            return ExitCode::USAGE;
        }

        $affiliates = array_values(array_filter(
            AffiliateElement::find()->status(null)->all(),
            static fn(AffiliateElement $a) => $a->affiliateStatus === AffiliateElement::STATUS_ACTIVE
        ));

        if ($affiliates === []) {
            $this->stderr("No active affiliates available. Run `craft kickback/seed --fresh` first.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $summary = [
            'countRequested' => $this->count,
            'seed' => $this->seed,
            'dryRun' => $this->dryRun,
            'mix' => $weights,
            'simulated' => 0,
            'createdReferrals' => 0,
            'createdCommissions' => 0,
            'skippedNoRule' => 0,
            'mismatches' => 0,
            'ruleAppliedCounts' => [],
        ];

        $rows = [];

        for ($i = 0; $i < $this->count; $i++) {
            $affiliate = $affiliates[array_rand($affiliates)];
            $scenario = SimulationHelper::pickWeighted($weights) ?? 'program';
            $subtotal = round(mt_rand(2500, 60000) / 100, 2);

            $resolution = $this->resolveRateForScenario($affiliate, $scenario, $plugin);
            if ($resolution === null) {
                $summary['skippedNoRule']++;
                continue;
            }

            [$rate, $rateType, $ruleApplied] = $resolution;
            $amount = $plugin->commissions->calculateAmount($subtotal, $rate, $rateType);

            $summary['simulated']++;
            $summary['ruleAppliedCounts'][$ruleApplied] = ($summary['ruleAppliedCounts'][$ruleApplied] ?? 0) + 1;

            $row = [
                'scenario' => $scenario,
                'affiliateId' => $affiliate->id,
                'programId' => $affiliate->programId,
                'subtotal' => $subtotal,
                'rate' => $rate,
                'rateType' => $rateType,
                'ruleApplied' => $ruleApplied,
                'expectedAmount' => $amount,
            ];

            if (!$this->dryRun) {
                $referral = new ReferralElement();
                $referral->affiliateId = $affiliate->id;
                $referral->programId = $affiliate->programId;
                $referral->orderId = 900000 + $this->seed * 1000 + $i;
                $referral->customerEmail = "sim+{$this->seed}-{$i}@example.test";
                $referral->orderSubtotal = $subtotal;
                $referral->referralStatus = ReferralElement::STATUS_APPROVED;
                $referral->attributionMethod = ReferralElement::ATTRIBUTION_MANUAL;
                $referral->dateApproved = new \DateTime();
                $referralTrace = [
                    'source' => 'kickback/simulate/run',
                    'scenario' => $scenario,
                    'seed' => $this->seed,
                    'resolved' => [
                        'affiliateId' => $affiliate->id,
                        'method' => ReferralElement::ATTRIBUTION_MANUAL,
                        'orderSubtotal' => $subtotal,
                    ],
                ];
                $encodedReferralTrace = json_encode($referralTrace, JSON_UNESCAPED_SLASHES);
                $referral->referralResolutionTrace = is_string($encodedReferralTrace) ? $encodedReferralTrace : null;

                if (!Craft::$app->getElements()->saveElement($referral, false)) {
                    $summary['mismatches']++;
                    $row['error'] = 'referral_save_failed';
                    $row['errors'] = $referral->getErrors();
                    $rows[] = $row;
                    continue;
                }
                $summary['createdReferrals']++;

                $commission = new CommissionElement();
                $commission->referralId = $referral->id;
                $commission->affiliateId = $affiliate->id;
                $commission->amount = $amount;
                $commission->currency = KickBack::getCommerceCurrency();
                $commission->rate = $rate;
                $commission->rateType = $rateType;
                $commission->ruleApplied = $ruleApplied;
                $commissionTrace = [
                    'source' => 'kickback/simulate/run',
                    'scenario' => $scenario,
                    'seed' => $this->seed,
                    'resolved' => [
                        'rate' => $rate,
                        'rateType' => $rateType,
                        'ruleApplied' => $ruleApplied,
                        'subtotal' => $subtotal,
                        'amount' => $amount,
                    ],
                ];
                $encodedCommissionTrace = json_encode($commissionTrace, JSON_UNESCAPED_SLASHES);
                $commission->ruleResolutionTrace = is_string($encodedCommissionTrace) ? $encodedCommissionTrace : null;
                $commission->tier = 1;
                $commission->commissionStatus = CommissionElement::STATUS_APPROVED;
                $commission->dateApproved = new \DateTime();

                if (!Craft::$app->getElements()->saveElement($commission, false)) {
                    $summary['mismatches']++;
                    $row['error'] = 'commission_save_failed';
                    $row['errors'] = $commission->getErrors();
                    $rows[] = $row;
                    continue;
                }
                $summary['createdCommissions']++;
            }

            $rows[] = $row;
        }

        $this->stdout(sprintf(
            "Simulation complete. simulated=%d createdReferrals=%d createdCommissions=%d skippedNoRule=%d mismatches=%d dryRun=%s\n",
            $summary['simulated'],
            $summary['createdReferrals'],
            $summary['createdCommissions'],
            $summary['skippedNoRule'],
            $summary['mismatches'],
            $summary['dryRun'] ? 'true' : 'false',
        ));

        if ($summary['ruleAppliedCounts'] !== []) {
            $this->stdout("Rule distribution:\n");
            foreach ($summary['ruleAppliedCounts'] as $rule => $count) {
                $this->stdout("  - {$rule}: {$count}\n");
            }
        }

        if ($this->report !== null) {
            $payload = [
                'summary' => $summary,
                'rows' => $rows,
                'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
            file_put_contents($this->report, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->stdout("Wrote report to {$this->report}\n");
        }

        return $summary['mismatches'] > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * @return array{0: float, 1: string, 2: string}|null
     */
    private function resolveRateForScenario(AffiliateElement $affiliate, string $scenario, KickBack $plugin): ?array
    {
        $program = $plugin->programs->getProgramById($affiliate->programId);
        $settings = $plugin->getSettings();

        return match ($scenario) {
            'product' => $this->resolveTopRuleByType($affiliate->programId, CommissionRuleElement::TYPE_PRODUCT, 'rule:product'),
            'category' => $this->resolveTopRuleByType($affiliate->programId, CommissionRuleElement::TYPE_CATEGORY, 'rule:category'),
            'tiered' => (($rule = $plugin->commissionRules->findTieredRule($affiliate->programId, $affiliate->id)) !== null)
                ? [$rule->commissionRate, $rule->commissionType, 'rule:tiered:' . $rule->name]
                : null,
            'bonus' => (($rule = $plugin->commissionRules->findBonusRule($affiliate->programId)) !== null)
                ? [$rule->commissionRate, $rule->commissionType, 'rule:bonus:' . $rule->name]
                : null,
            'group' => ($affiliate->groupId !== null && ($group = $plugin->affiliateGroups->getGroupById($affiliate->groupId)) !== null)
                ? [$group->commissionRate, $group->commissionType, 'group:' . $group->handle]
                : null,
            'program' => ($program !== null)
                ? [$program->defaultCommissionRate, $program->defaultCommissionType, 'program:' . $program->handle]
                : null,
            'default' => [$settings->defaultCommissionRate, $settings->defaultCommissionType, 'global_default'],
            default => ($program !== null)
                ? [$program->defaultCommissionRate, $program->defaultCommissionType, 'program:' . $program->handle]
                : [$settings->defaultCommissionRate, $settings->defaultCommissionType, 'global_default'],
        };
    }

    /**
     * @return array{0: float, 1: string, 2: string}|null
     */
    private function resolveTopRuleByType(int $programId, string $type, string $prefix): ?array
    {
        $rule = CommissionRuleElement::find()
            ->programId($programId)
            ->type($type)
            ->orderBy([
                'kickback_commission_rules.priority' => SORT_DESC,
                'elements.dateCreated' => SORT_DESC,
            ])
            ->one();

        if ($rule === null) {
            return null;
        }

        return [$rule->commissionRate, $rule->commissionType, $prefix . ':' . $rule->name];
    }
}
