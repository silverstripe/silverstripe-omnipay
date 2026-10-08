<?php

namespace SilverStripe\Omnipay\Stripe;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Omnipay\GatewayFieldsProvider;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Helper\PaymentMoney;
use SilverStripe\View\Requirements;

/**
 * Stripe Payment Element fields for {@link \Omnipay\Stripe\PaymentIntentsGateway} (`Stripe_PaymentIntents`).
 *
 * Replaces the credit card fields of {@link GatewayFieldsFactory} with a mount node for the
 * {@link https://docs.stripe.com/payments/payment-element Stripe Payment Element} and a hidden `paymentMethod`
 * field. When the form is submitted, `client/js/stripe-payment-element.js` creates a payment method from the
 * Payment Element and stores its ID (`pm_…`) in the hidden field, which is then passed to Omnipay.
 *
 * The Payment Element is initialised without a PaymentIntent (Stripe's "deferred intent" mode). The only
 * PaymentIntent is the one Omnipay creates and confirms when the payment is initiated.
 *
 * Requires `omnipay/stripe`. The gateway is configured in {@link GatewayInfo}:
 *
 * <code>
 * SilverStripe\Omnipay\GatewayInfo:
 *   Stripe_PaymentIntents:
 *     parameters:
 *       apiKey: '`STRIPE_SECRET_KEY`'
 *       stripe_publishable_key: '`STRIPE_PUBLISHABLE_KEY`'
 * </code>
 *
 * Set {@link GatewayFieldsFactory::setPaymentAmount()} and {@link GatewayFieldsFactory::setPaymentCurrency()}
 * before calling {@link GatewayFieldsFactory::getFields()}. The Payment Element uses them to show the payment
 * methods that are available for the payment.
 *
 * The mount node and the Appearance API options can be configured on this class:
 *
 * <code>
 * SilverStripe\Omnipay\Stripe\StripeGatewayFieldsProvider:
 *   stripe_payment_element_mount_id: 'my-mount'
 *   stripe_payment_element_mount_extra_classes: 'my-extra-class'
 *   stripe_payment_element_appearance:
 *     theme: 'stripe'
 * </code>
 */
class StripeGatewayFieldsProvider implements GatewayFieldsProvider
{
    use Configurable;
    use Injectable;

    /**
     * Omnipay class of the Stripe Payment Intents gateway (`Stripe_PaymentIntents`).
     */
    public const PAYMENT_INTENTS_GATEWAY_CLASS = 'Omnipay\Stripe\PaymentIntentsGateway';

    /**
     * Stripe.js v3 bundle URL (see Stripe docs).
     */
    private const STRIPE_JS_URL = 'https://js.stripe.com/v3/';

    /**
     * @config Appearance API options for the Payment Element.
     * See https://docs.stripe.com/elements/appearance-api
     *
     * @var array<string, mixed>
     */
    private static array $stripe_payment_element_appearance = [];

    /**
     * @config HTML id attribute for the Payment Element mount node (without `#`).
     */
    private static string $stripe_payment_element_mount_id = 'stripe-payment-element';

    /**
     * @config Extra CSS classes for the mount container (styling hook).
     */
    private static string $stripe_payment_element_mount_extra_classes = '';

    /**
     * Whether the given gateway uses {@link \Omnipay\Stripe\PaymentIntentsGateway}. Accepts gateway names
     * configured with a `gateway_class`, Omnipay short names and class names.
     */
    public static function isPaymentIntentsGateway(?string $gateway): bool
    {
        if (!$gateway) {
            return false;
        }

        return GatewayFieldsFactory::normalizeGatewayClass(GatewayInfo::getGatewayClass($gateway))
            === self::PAYMENT_INTENTS_GATEWAY_CLASS;
    }

    public function providesCardFields(GatewayFieldsFactory $factory): bool
    {
        return self::isPaymentIntentsGateway($factory->getGateway());
    }

    public function getRequiredCardFieldsForGateway(string $gateway): ?array
    {
        if (!self::isPaymentIntentsGateway($gateway)) {
            return null;
        }

        return ['paymentMethod'];
    }

    /**
     * @throws InvalidConfigurationException when the publishable key, amount or currency are missing
     */
    public function getCardFields(GatewayFieldsFactory $factory): FieldList
    {
        $gateway = (string) $factory->getGateway();

        $publishableKey = $this->getPublishableKey($gateway);
        $options = $this->getPaymentElementOptions($factory, $gateway);

        $this->requireStripePaymentElementAssets();

        $mountId = self::config()->get('stripe_payment_element_mount_id');
        $mountId = is_string($mountId) && $mountId !== '' ? $mountId : 'stripe-payment-element';

        $extraClasses = trim((string) self::config()->get('stripe_payment_element_mount_extra_classes'));

        $html = sprintf(
            '<div class="%s" id="%s" data-stripe-payment-element="1" data-publishable-key="%s"'
            . ' data-options="%s" aria-live="polite"></div>',
            Convert::raw2att(trim('stripe-payment-element__mount ' . $extraClasses)),
            Convert::raw2att($mountId),
            Convert::raw2att($publishableKey),
            Convert::raw2att(json_encode($options, JSON_THROW_ON_ERROR))
        );

        $fields = FieldList::create(
            FieldGroup::create(
                _t(GatewayFieldsFactory::class . '.StripePaymentElementGroupTitle', 'Payment details'),
                LiteralField::create('StripePaymentElementMount', $html),
                HiddenField::create($factory->getFieldName('paymentMethod'), '')
                    ->addExtraClass('stripe-payment-element__payment-method'),
            )->addExtraClass('stripe-payment-element')
        );

        $factory->extend('updateStripePaymentElementFields', $fields, $gateway);

        return $fields;
    }

    /**
     * Options for `stripe.elements()`, see https://docs.stripe.com/js/elements_object/create_without_intent
     *
     * @return array<string, mixed>
     * @throws InvalidConfigurationException when amount or currency aren't set
     */
    public function getPaymentElementOptions(GatewayFieldsFactory $factory, string $gateway): array
    {
        $amount = $factory->getPaymentAmount();
        $currency = strtoupper(trim((string) $factory->getPaymentCurrency()));

        if ($amount === null || $amount <= 0 || $currency === '') {
            throw new InvalidConfigurationException(
                'The Stripe Payment Element requires a payment amount and currency. Call setPaymentAmount() and '
                . 'setPaymentCurrency() on the GatewayFieldsFactory before getFields().'
            );
        }

        $options = [
            'mode' => 'payment',
            // Stripe expects the amount in the smallest currency unit (eg. cents, or yen for JPY)
            'amount' => (int) PaymentMoney::toMoney($amount, $currency)->getAmount(),
            'currency' => strtolower($currency),
            // The payment method is created in the browser and the PaymentIntent is confirmed by Omnipay
            'paymentMethodCreation' => 'manual',
        ];

        // Omnipay authorizes with capture_method "manual", which the Payment Element needs to know about to only
        // offer payment methods that support it
        if (GatewayInfo::shouldUseAuthorize($gateway)) {
            $options['captureMethod'] = 'manual';
        }

        $appearance = self::config()->get('stripe_payment_element_appearance');
        if (is_array($appearance) && $appearance !== []) {
            $options['appearance'] = $appearance;
        }

        $factory->extend('updateStripePaymentElementOptions', $options, $gateway);

        return $options;
    }

    /**
     * @throws InvalidConfigurationException when the publishable key isn't configured
     */
    protected function getPublishableKey(string $gateway): string
    {
        $params = GatewayInfo::getParameters($gateway) ?? [];
        $key = $params['stripe_publishable_key'] ?? null;

        if (!is_string($key) || $key === '') {
            throw new InvalidConfigurationException(sprintf(
                'Gateway "%s" requires the "stripe_publishable_key" parameter for the Stripe Payment Element.',
                $gateway
            ));
        }

        return $key;
    }

    /**
     * Register Stripe.js and the Payment Element bootstrap script for the current response.
     */
    protected function requireStripePaymentElementAssets(): void
    {
        Requirements::javascript(self::STRIPE_JS_URL);
        Requirements::javascript('silverstripe/silverstripe-omnipay: client/js/stripe-payment-element.js');
    }
}
