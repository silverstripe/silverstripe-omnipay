<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Stripe;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Stripe\StripeGatewayFieldsProvider;
use SilverStripe\View\Requirements;

class StripeGatewayFieldsProviderTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();

        Requirements::clear();

        Config::modify()->remove(GatewayFieldsFactory::class, 'rename');
        Config::modify()->set(GatewayFieldsFactory::class, 'gateway_fields_providers', [
            'Stripe_PaymentIntents' => StripeGatewayFieldsProvider::class,
        ]);
        Config::modify()->set(GatewayInfo::class, 'Stripe_PaymentIntents', [
            'parameters' => [
                'apiKey' => 'sk_test_secret',
                'stripe_publishable_key' => 'pk_test_publishable',
            ],
        ]);
    }

    public function testIsPaymentIntentsGateway(): void
    {
        Config::modify()->set(GatewayInfo::class, 'Stripe_Donations', [
            'gateway_class' => 'Stripe_PaymentIntents',
        ]);

        $this->assertTrue(StripeGatewayFieldsProvider::isPaymentIntentsGateway('Stripe_PaymentIntents'));
        $this->assertTrue(StripeGatewayFieldsProvider::isPaymentIntentsGateway('Omnipay\Stripe\PaymentIntentsGateway'));
        $this->assertTrue(StripeGatewayFieldsProvider::isPaymentIntentsGateway('\Omnipay\Stripe\PaymentIntentsGateway'));
        $this->assertTrue(StripeGatewayFieldsProvider::isPaymentIntentsGateway('Stripe_Donations'));

        $this->assertFalse(StripeGatewayFieldsProvider::isPaymentIntentsGateway('Stripe'));
        $this->assertFalse(StripeGatewayFieldsProvider::isPaymentIntentsGateway('Dummy'));
        $this->assertFalse(StripeGatewayFieldsProvider::isPaymentIntentsGateway(null));
    }

    public function testProviderLookup(): void
    {
        Config::modify()->set(GatewayInfo::class, 'Stripe_Donations', [
            'gateway_class' => 'Stripe_PaymentIntents',
        ]);

        $this->assertInstanceOf(
            StripeGatewayFieldsProvider::class,
            GatewayFieldsFactory::getGatewayFieldsProviderForGateway('Stripe_PaymentIntents')
        );
        $this->assertInstanceOf(
            StripeGatewayFieldsProvider::class,
            GatewayFieldsFactory::getGatewayFieldsProviderForGateway('\Omnipay\Stripe\PaymentIntentsGateway'),
            'Omnipay class names resolve to the provider of the short name'
        );
        $this->assertInstanceOf(
            StripeGatewayFieldsProvider::class,
            GatewayFieldsFactory::getGatewayFieldsProviderForGateway('Stripe_Donations'),
            'Gateways with a gateway_class use the provider of that class'
        );
        $this->assertNull(GatewayFieldsFactory::getGatewayFieldsProviderForGateway('Dummy'));
        $this->assertNull(GatewayFieldsFactory::getGatewayFieldsProviderForGateway(null));

        // A gateway can opt out of a provider configured for its class
        Config::modify()->merge(GatewayFieldsFactory::class, 'gateway_fields_providers', [
            'Stripe_Donations' => null,
        ]);
        $this->assertNull(GatewayFieldsFactory::getGatewayFieldsProviderForGateway('Stripe_Donations'));
    }

    public function testRequiredFields(): void
    {
        Config::modify()->set(GatewayInfo::class, 'Stripe_Donations', [
            'gateway_class' => 'Stripe_PaymentIntents',
            'required_fields' => ['email'],
        ]);

        $this->assertSame(['paymentMethod'], GatewayInfo::requiredFields('Stripe_PaymentIntents'));
        $this->assertSame(['email', 'paymentMethod'], GatewayInfo::requiredFields('Stripe_Donations'));

        $validator = GatewayFieldsFactory::create('Stripe_PaymentIntents')->getValidator();
        $this->assertSame(['paymentMethod'], $validator->getRequired());
    }

    public function testCardFields(): void
    {
        $factory = GatewayFieldsFactory::create('Stripe_PaymentIntents', ['Card'])
            ->setPaymentAmount(10.5)
            ->setPaymentCurrency('NZD');

        $fields = $factory->getFields();

        $this->assertNull($fields->dataFieldByName('number'), 'Standard card fields are replaced');

        $hidden = $fields->dataFieldByName('paymentMethod');
        $this->assertInstanceOf(HiddenField::class, $hidden);
        $this->assertTrue($hidden->hasClass('stripe-payment-element__payment-method'));

        $html = $this->getMountHtml($fields);
        $this->assertStringContainsString('id="stripe-payment-element"', $html);
        $this->assertStringContainsString('data-publishable-key="pk_test_publishable"', $html);
        $this->assertStringNotContainsString('sk_test_secret', $html, 'The secret key must never be rendered');
        $this->assertSame([
            'mode' => 'payment',
            'amount' => 1050,
            'currency' => 'nzd',
            'paymentMethodCreation' => 'manual',
        ], $this->getOptions($html));

        $scripts = array_keys(Requirements::backend()->getJavascript());
        $this->assertContains('https://js.stripe.com/v3/', $scripts);
        $this->assertNotEmpty(array_filter(
            $scripts,
            fn ($script) => str_contains($script, 'client/js/stripe-payment-element.js')
        ));
    }

    public function testCardFieldsConfig(): void
    {
        Config::modify()->set(GatewayFieldsFactory::class, 'rename', ['paymentMethod' => 'StripePM']);
        Config::modify()->set(StripeGatewayFieldsProvider::class, 'stripe_payment_element_mount_id', 'my-mount');
        Config::modify()->set(StripeGatewayFieldsProvider::class, 'stripe_payment_element_mount_extra_classes', 'big');
        Config::modify()->set(StripeGatewayFieldsProvider::class, 'stripe_payment_element_appearance', [
            'theme' => 'night',
        ]);

        $fields = GatewayFieldsFactory::create('Stripe_PaymentIntents', ['Card'])
            ->setPaymentAmount(10)
            ->setPaymentCurrency('USD')
            ->getFields();

        $this->assertNotNull($fields->dataFieldByName('StripePM'), 'The paymentMethod field can be renamed');

        $html = $this->getMountHtml($fields);
        $this->assertStringContainsString('id="my-mount"', $html);
        $this->assertStringContainsString('class="stripe-payment-element__mount big"', $html);
        $this->assertSame(['theme' => 'night'], $this->getOptions($html)['appearance']);
    }

    public function testAuthorizeUsesManualCapture(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'Stripe_PaymentIntents', ['use_authorize' => true]);

        $options = StripeGatewayFieldsProvider::create()->getPaymentElementOptions(
            GatewayFieldsFactory::create()->setPaymentAmount(10)->setPaymentCurrency('USD'),
            'Stripe_PaymentIntents'
        );

        $this->assertSame('manual', $options['captureMethod']);
    }

    public function testCardFieldsForOtherGatewaysAreUnchanged(): void
    {
        Config::modify()->set(GatewayInfo::class, 'Dummy', ['is_offsite' => false]);

        $fields = GatewayFieldsFactory::create('Dummy', ['Card'])->getFields();

        $this->assertNotNull($fields->dataFieldByName('number'));
        $this->assertNull($fields->dataFieldByName('paymentMethod'));
        $this->assertEmpty(Requirements::backend()->getJavascript());
    }

    public function testAmountUsesMinorUnitsOfCurrency(): void
    {
        $provider = StripeGatewayFieldsProvider::create();

        $options = $provider->getPaymentElementOptions(
            GatewayFieldsFactory::create()->setPaymentAmount(1500)->setPaymentCurrency('jpy'),
            'Stripe_PaymentIntents'
        );
        $this->assertSame(1500, $options['amount']);
        $this->assertSame('jpy', $options['currency']);
        $this->assertArrayNotHasKey('captureMethod', $options);

        $options = $provider->getPaymentElementOptions(
            GatewayFieldsFactory::create()->setPaymentAmount(19.99)->setPaymentCurrency('USD'),
            'Stripe_PaymentIntents'
        );
        $this->assertSame(1999, $options['amount']);
    }

    public function testExtensionHooks(): void
    {
        GatewayFieldsFactory::add_extension(TestStripePaymentElementExtension::class);

        try {
            $fields = GatewayFieldsFactory::create('Stripe_PaymentIntents', ['Card'])
                ->setPaymentAmount(10)
                ->setPaymentCurrency('USD')
                ->getFields();
        } finally {
            GatewayFieldsFactory::remove_extension(TestStripePaymentElementExtension::class);
        }

        $this->assertNotNull($fields->fieldByName('StripeNotice'));
        $this->assertSame(['card'], $this->getOptions($this->getMountHtml($fields))['paymentMethodTypes']);
    }

    public function testMissingAmountThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        GatewayFieldsFactory::create('Stripe_PaymentIntents', ['Card'])
            ->setPaymentCurrency('USD')
            ->getFields();
    }

    public function testMissingCurrencyThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        GatewayFieldsFactory::create('Stripe_PaymentIntents', ['Card'])
            ->setPaymentAmount(10)
            ->getFields();
    }

    public function testMissingPublishableKeyThrows(): void
    {
        Config::modify()->set(GatewayInfo::class, 'Stripe_PaymentIntents', [
            'parameters' => ['apiKey' => 'sk_test_secret'],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('stripe_publishable_key');

        GatewayFieldsFactory::create('Stripe_PaymentIntents', ['Card'])
            ->setPaymentAmount(10)
            ->setPaymentCurrency('USD')
            ->getFields();
    }

    private function getMountHtml(FieldList $fields): string
    {
        $mount = $fields->flattenFields()->fieldByName('StripePaymentElementMount');
        $this->assertInstanceOf(LiteralField::class, $mount);
        return (string) $mount->getContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function getOptions(string $html): array
    {
        $this->assertSame(1, preg_match('/data-options="([^"]*)"/', $html, $matches));
        return json_decode(html_entity_decode($matches[1]), true, 512, JSON_THROW_ON_ERROR);
    }
}
