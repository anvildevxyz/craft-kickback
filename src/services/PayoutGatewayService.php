<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\gateways\ManualGateway;
use anvildev\craftkickback\gateways\PayoutGatewayInterface;
use anvildev\craftkickback\gateways\PayPalGateway;
use anvildev\craftkickback\gateways\StripeGateway;
use craft\base\Component;

/**
 * Registers and provides access to payout payment gateways.
 */
class PayoutGatewayService extends Component
{
    /** @var PayoutGatewayInterface[] */
    private array $gateways = [];

    public function init(): void
    {
        parent::init();

        $this->gateways = [
            PayoutElement::METHOD_PAYPAL => new PayPalGateway(),
            PayoutElement::METHOD_STRIPE => new StripeGateway(),
            PayoutElement::METHOD_MANUAL => new ManualGateway(),
        ];
    }

    public function getGateway(string $handle): ?PayoutGatewayInterface
    {
        return $this->gateways[$handle] ?? null;
    }

    /**
     * @return PayoutGatewayInterface[]
     */
    public function getConfiguredGateways(): array
    {
        return array_filter(
            $this->gateways,
            fn(PayoutGatewayInterface $gw) => $gw->isConfigured(),
        );
    }

    /**
     * Typed accessor for Stripe-specific methods (onboarding, account checks).
     */
    public function getStripeGateway(): ?StripeGateway
    {
        $gw = $this->gateways[PayoutElement::METHOD_STRIPE] ?? null;

        return $gw instanceof StripeGateway ? $gw : null;
    }
}
