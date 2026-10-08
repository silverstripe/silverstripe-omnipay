<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Stripe;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Omnipay\GatewayFieldsFactory;

/**
 * @extends Extension<GatewayFieldsFactory>
 */
class TestStripePaymentElementExtension extends Extension implements TestOnly
{
    /**
     * @param array<string, mixed> $options
     */
    protected function updateStripePaymentElementOptions(array &$options, string $gateway): void
    {
        $options['paymentMethodTypes'] = ['card'];
    }

    protected function updateStripePaymentElementFields(FieldList $fields, string $gateway): void
    {
        $fields->push(LiteralField::create('StripeNotice', 'Payments are processed by Stripe'));
    }
}
