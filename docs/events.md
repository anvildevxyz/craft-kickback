# Events

Kickback fires events at key lifecycle points, allowing you to hook into affiliate, referral, commission, payout, and fraud workflows. All events use the `before`/`after` pattern - `before` events can be cancelled by setting `$event->isValid = false`.

## Event Classes

All event classes extend `yii\base\ModelEvent` and live in `anvildev\craftkickback\events`.

### AffiliateEvent

```php
use anvildev\craftkickback\events\AffiliateEvent;

// Properties
$event->affiliate;  // AffiliateElement
$event->isNew;      // bool
$event->isValid;    // bool (set to false in "before" events to cancel)
```

### ReferralEvent

```php
use anvildev\craftkickback\events\ReferralEvent;

// Properties
$event->affiliate;  // ?AffiliateElement
$event->isNew;      // bool
$event->isValid;    // bool
```

### CommissionEvent

```php
use anvildev\craftkickback\events\CommissionEvent;

// Properties
$event->commission;  // ?CommissionRecord
$event->affiliate;   // ?AffiliateElement
$event->element;     // ?CommissionElement (populated in create events; null in approve/reject/reverse events)
$event->isValid;     // bool
```

### PayoutEvent

```php
use anvildev\craftkickback\events\PayoutEvent;

// Properties
$event->payout;     // ?Payout
$event->affiliate;  // ?AffiliateElement
$event->isValid;    // bool
```

### FraudEvent

```php
use anvildev\craftkickback\events\FraudEvent;

// Properties
$event->referral;    // ?ReferralRecord
$event->fraudFlags;  // string[]
$event->isValid;     // bool
```

## Affiliate Events

Fired by `AffiliateService`:

| Constant | When |
|---|---|
| `EVENT_BEFORE_APPROVE_AFFILIATE` | Before an affiliate is approved (cancellable) |
| `EVENT_AFTER_APPROVE_AFFILIATE` | After an affiliate is approved |
| `EVENT_BEFORE_REJECT_AFFILIATE` | Before an affiliate is rejected (cancellable) |
| `EVENT_AFTER_REJECT_AFFILIATE` | After an affiliate is rejected |
| `EVENT_BEFORE_SUSPEND_AFFILIATE` | Before an affiliate is suspended (cancellable) |
| `EVENT_AFTER_SUSPEND_AFFILIATE` | After an affiliate is suspended |
| `EVENT_BEFORE_REACTIVATE_AFFILIATE` | Before a suspended affiliate is reactivated (cancellable) |
| `EVENT_AFTER_REACTIVATE_AFFILIATE` | After a suspended affiliate is reactivated |

### Example: Sync affiliate status to external CRM

```php
use anvildev\craftkickback\services\AffiliateService;
use anvildev\craftkickback\events\AffiliateEvent;
use yii\base\Event;

Event::on(
    AffiliateService::class,
    AffiliateService::EVENT_AFTER_APPROVE_AFFILIATE,
    function (AffiliateEvent $event) {
        $affiliate = $event->affiliate;
        $user = $affiliate->getUser();

        // Sync to your CRM
        MyCrm::updateContact($user->email, [
            'affiliate_status' => 'active',
            'referral_code' => $affiliate->referralCode,
        ]);
    }
);
```

### Example: Prevent approval based on custom criteria

```php
Event::on(
    AffiliateService::class,
    AffiliateService::EVENT_BEFORE_APPROVE_AFFILIATE,
    function (AffiliateEvent $event) {
        $user = $event->affiliate->getUser();

        // Require profile photo before approval
        if (!$user->getPhoto()) {
            $event->isValid = false;
        }
    }
);
```

## Referral Events

Fired by `ReferralService`:

| Constant | When |
|---|---|
| `EVENT_BEFORE_CREATE_REFERRAL` | Before a referral is created from an order (cancellable) |
| `EVENT_AFTER_CREATE_REFERRAL` | After a referral is created |
| `EVENT_BEFORE_APPROVE_REFERRAL` | Before a referral is approved (cancellable) |
| `EVENT_AFTER_APPROVE_REFERRAL` | After a referral is approved |
| `EVENT_BEFORE_REJECT_REFERRAL` | Before a referral is rejected (cancellable) |
| `EVENT_AFTER_REJECT_REFERRAL` | After a referral is rejected |

### Example: Block referrals from specific countries

```php
use anvildev\craftkickback\services\ReferralService;
use anvildev\craftkickback\events\ReferralEvent;

Event::on(
    ReferralService::class,
    ReferralService::EVENT_BEFORE_CREATE_REFERRAL,
    function (ReferralEvent $event) {
        // Cancel referral creation for blocked regions
        $blockedCountries = ['XX', 'YY'];
        $ip = Craft::$app->getRequest()->getUserIP();
        $country = MyGeoService::lookup($ip);

        if (in_array($country, $blockedCountries, true)) {
            $event->isValid = false;
        }
    }
);
```

## Commission Events

Fired by `CommissionService`:

| Constant | When |
|---|---|
| `EVENT_BEFORE_CREATE_COMMISSION` | Before a commission is saved for the first time (cancellable) |
| `EVENT_AFTER_CREATE_COMMISSION` | After a commission is saved |
| `EVENT_BEFORE_APPROVE_COMMISSION` | Before a commission is approved (cancellable) |
| `EVENT_AFTER_APPROVE_COMMISSION` | After a commission is approved |
| `EVENT_BEFORE_REJECT_COMMISSION` | Before a commission is rejected (cancellable) |
| `EVENT_AFTER_REJECT_COMMISSION` | After a commission is rejected |
| `EVENT_BEFORE_REVERSE_COMMISSION` | Before a commission is reversed (cancellable) |
| `EVENT_AFTER_REVERSE_COMMISSION` | After a commission is reversed |

### Create event payload

`EVENT_BEFORE_CREATE_COMMISSION` fires before the commission record is written to the database. At that point `$event->commission` is `null` (no ID yet); the in-memory data is available on `$event->element` (`CommissionElement`) and `$event->affiliate` (`AffiliateElement`).

`EVENT_AFTER_CREATE_COMMISSION` fires after a successful save. `$event->commission` (`CommissionRecord`) is populated alongside `$event->element` and `$event->affiliate`.

For multi-tier commissions (`createMultiTierCommissions()`), one event pair fires per commission created - not once per batch - so listeners can veto or observe each tier independently.

**Veto semantics:** Setting `$event->isValid = false` in a `BEFORE_CREATE_COMMISSION` listener causes `saveCommission()` to throw a `\RuntimeException` inside its DB transaction, which rolls back the record. Callers of `createCommission()` and `createMultiTierCommissions()` that need to continue processing the rest of an order's commissions after a single veto should wrap the call in a try/catch for `\RuntimeException`.

### Example: Veto commissions above a threshold

```php
use anvildev\craftkickback\events\CommissionEvent;
use anvildev\craftkickback\services\CommissionService;
use yii\base\Event;

Event::on(
    CommissionService::class,
    CommissionService::EVENT_BEFORE_CREATE_COMMISSION,
    function (CommissionEvent $event) {
        if ($event->element !== null && $event->element->amount > 1000) {
            // Reject any commission over $1000 for manual review.
            $event->isValid = false;
        }
    },
);
```

### Example: Log commission approvals

```php
use anvildev\craftkickback\services\CommissionService;
use anvildev\craftkickback\events\CommissionEvent;

Event::on(
    CommissionService::class,
    CommissionService::EVENT_AFTER_APPROVE_COMMISSION,
    function (CommissionEvent $event) {
        Craft::info(
            "Commission #{$event->commission->id} approved: " .
            "{$event->commission->amount} for affiliate #{$event->affiliate->id}",
            'my-module'
        );
    }
);
```

## Payout Events

Fired by `PayoutService`:

| Constant | When |
|---|---|
| `EVENT_BEFORE_CREATE_PAYOUT` | Before a payout is created (cancellable) |
| `EVENT_AFTER_CREATE_PAYOUT` | After a payout is created |
| `EVENT_BEFORE_PROCESS_PAYOUT` | Before a payout is processed via gateway (cancellable) |
| `EVENT_AFTER_PROCESS_PAYOUT` | After a payout is completed |

### Example: Add a minimum payout cap

```php
use anvildev\craftkickback\services\PayoutService;
use anvildev\craftkickback\events\PayoutEvent;

Event::on(
    PayoutService::class,
    PayoutService::EVENT_BEFORE_CREATE_PAYOUT,
    function (PayoutEvent $event) {
        // Cap individual payouts at $10,000
        if ($event->payout->amount > 10000) {
            $event->isValid = false;
        }
    }
);
```

## Fraud Events

Fired by `FraudService`:

| Constant | When |
|---|---|
| `EVENT_AFTER_FLAG_REFERRAL` | After a referral is flagged for fraud |
| `EVENT_AFTER_APPROVE_FLAGGED` | After a flagged referral is approved via manual review |
| `EVENT_AFTER_REJECT_FLAGGED` | After a flagged referral is rejected |

### Example: Send Slack alert on fraud detection

```php
use anvildev\craftkickback\services\FraudService;
use anvildev\craftkickback\events\FraudEvent;

Event::on(
    FraudService::class,
    FraudService::EVENT_AFTER_FLAG_REFERRAL,
    function (FraudEvent $event) {
        $flags = implode(', ', $event->fraudFlags);
        MySlackService::send(
            "#fraud-alerts",
            "Referral #{$event->referral->id} flagged: {$flags}"
        );
    }
);
```

## Approval Events

`ApprovalService` fires three events using a plain `yii\base\Event` (no custom payload):

| Constant | String value | When |
|---|---|---|
| `EVENT_AFTER_REQUEST` | `afterRequest` | After an approval row is created via `request()` |
| `EVENT_AFTER_APPROVE` | `afterApprove` | After an approval is resolved as approved |
| `EVENT_AFTER_REJECT` | `afterReject` | After an approval is rejected |

None of these events are cancellable - there are no `before` variants. State-change side-effects on the target (such as updating a payout's status on rejection) are handled by the target handler's `ApprovalTargetInterface::onReject()` hook, which runs inside the same DB transaction as the `reject()` call, before the `EVENT_AFTER_REJECT` event fires.

## Notification Events

The plugin automatically listens to these events for email notifications:

| Event | Notification |
|---|---|
| `AffiliateService::EVENT_AFTER_APPROVE_AFFILIATE` | Email affiliate: "You've been approved" |
| `AffiliateService::EVENT_AFTER_REJECT_AFFILIATE` | Email affiliate: "Your application was not approved" |
| `PayoutService::EVENT_AFTER_PROCESS_PAYOUT` | Email affiliate: "Payout completed" |
| `FraudService::EVENT_AFTER_FLAG_REFERRAL` | Email admin: "Referral flagged for fraud" |
