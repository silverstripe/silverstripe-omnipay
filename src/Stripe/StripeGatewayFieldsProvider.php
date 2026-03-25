<?php

namespace SilverStripe\Omnipay\Stripe;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Omnipay\GatewayFieldsProvider;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\View\Requirements;
use Stripe\PaymentIntent;
use Stripe\Stripe;

/**
 * Stripe Payment Element fields for {@link \Omnipay\Stripe\PaymentIntentsGateway}.
 *
 * Map this provider to `Stripe_PaymentIntents` in {@link GatewayFieldsFactory} configuration and use
 * {@link GatewayFieldsFactory} with that gateway (Omnipay short name or class name).
 *
 * Creates a Stripe {@link PaymentIntent} server-side (requires `stripe/stripe-php`) and passes
 * `client_secret` to the mount node for {@link https://docs.stripe.com/js}. Set
 * {@link GatewayFieldsFactory::setPaymentAmount()} / {@link GatewayFieldsFactory::setPaymentCurrency()}
 * before {@link GatewayFieldsFactory::getFields()} so the intent amount matches the payable.
 *
 * Configure appearance and mount options on this class in YAML, for example:
 *
 * <code>
 * SilverStripe\Omnipay\Stripe\StripeGatewayFieldsProvider:
 *   stripe_publishable_key: 'pk_test_...'
 *   stripe_payment_element_mount_id: 'my-mount'
 *   stripe_payment_element_mount_extra_classes: 'my-extra-class'
 *   stripe_payment_element_appearance:
 *     theme: 'stripe'
 * </code>
 *
 * The secret API key is read from `SilverStripe\Omnipay\GatewayInfo` gateway `parameters.apiKey`
 * (same as Omnipay).
 */
class StripeGatewayFieldsProvider implements GatewayFieldsProvider
{
    use Configurable;

    /**
     * Stripe.js v3 bundle URL (see Stripe docs).
     */
    private const STRIPE_JS_URL = 'https://js.stripe.com/v3/';

    /**
     * @config
     *
     * Default Appearance API options for the Payment Element (merged with per-request values).
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
     * Omnipay short name for {@link \Omnipay\Stripe\PaymentIntentsGateway}.
     */
    private const PAYMENT_INTENTS_SHORT_NAME = 'Stripe_PaymentIntents';

    private const PAYMENT_INTENTS_GATEWAY_CLASS = '\Omnipay\Stripe\PaymentIntentsGateway';

    private function isStripePaymentIntentsGateway(?string $gateway): bool
    {
        return $gateway === self::PAYMENT_INTENTS_SHORT_NAME
            || $gateway === self::PAYMENT_INTENTS_GATEWAY_CLASS;
    }

    public function providesCardFields(GatewayFieldsFactory $factory): bool
    {
        return $this->isStripePaymentIntentsGateway($factory->getGateway());
    }

    public function getRequiredCardFieldsForGateway(string $gateway): ?array
    {
        if (!$this->isStripePaymentIntentsGateway($gateway)) {
            return null;
        }

        return ['paymentMethod'];
    }

    public function getCardFields(GatewayFieldsFactory $factory): FieldList
    {
        if (!class_exists(Stripe::class)) {
            return FieldList::create();
        }

        $gateway = $factory->getGateway();

        if (!$gateway) {
            return FieldList::create();
        }

        $params = GatewayInfo::getParameters($gateway) ?? [];
        $publishableKey = $params['stripe_publishable_key'] ?? null;
        $privateKey = $params['stripe_secret_key'] ?? null;

        if (!is_string($publishableKey) || $publishableKey === '') {
            return FieldList::create([
                LiteralField::create('StripePaymentElementMount', '<div class="alert alert-danger">Stripe publishable key not set</div>'),
            ]);
        }

        Stripe::setApiKey($privateKey);

        $paymentIntent = $this->createPaymentIntent($factory, $gateway);

        if ($paymentIntent === null) {
            return FieldList::create([
                LiteralField::create('StripePaymentElementMount', '<div class="alert alert-danger">Payment intent could not be created</div>'),
            ]);
        }

        $clientSecret = $paymentIntent->client_secret ?? '';
        if (!is_string($clientSecret) || $clientSecret === '') {
            return FieldList::create();
        }

        $this->requireStripePaymentElementAssets();

        $mountId = self::config()->get('stripe_payment_element_mount_id') ?? 'stripe-payment-element';
        $mountId = is_string($mountId) ? $mountId : 'stripe-payment-element';

        $extraClasses = trim((string) (self::config()->get('stripe_payment_element_mount_extra_classes') ?? ''));

        $appearance = self::config()->get('stripe_payment_element_appearance') ?? [];
        if (!is_array($appearance)) {
            $appearance = [];
        }
        // Stripe Appearance API must be a JSON object {}; PHP's [] encodes as [] which parses as a JS array and breaks stripe.elements().
        $appearanceJson = $appearance === []
            ? '{}'
            : json_encode($appearance, JSON_THROW_ON_ERROR);
        $appearanceAttr = Convert::raw2att($appearanceJson);

        $containerClasses = trim('stripe-payment-element__mount ' . $extraClasses);

        $publishableAttr = sprintf(' data-publishable-key="%s"', Convert::raw2att($publishableKey));
        $clientSecretAttr = sprintf(' data-client-secret="%s"', Convert::raw2att($clientSecret));

        $html = sprintf(
            '<div class="%s" id="%s" data-stripe-payment-element="1" data-appearance="%s"%s%s aria-live="polite"></div>',
            Convert::raw2att($containerClasses),
            Convert::raw2att($mountId),
            $appearanceAttr,
            $publishableAttr,
            $clientSecretAttr
        );

        $literal = LiteralField::create('StripePaymentElementMount', $html);

        $hidden = HiddenField::create(
            $factory->getFieldName('paymentMethod'),
            ''
        )->addExtraClass('stripe-payment-element__payment-method');

        $fields = FieldList::create([
            FieldGroup::create(
                _t(GatewayFieldsFactory::class . '.StripePaymentElementGroupTitle', 'Payment details'),
                $literal,
                $hidden,
            )->addExtraClass('stripe-payment-element'),
        ]);

        $factory->extend('updateStripePaymentElementFields', $fields, $gateway);

        return $fields;
    }

    /**
     * @return \Stripe\PaymentIntent|null
     */
    private function createPaymentIntent(GatewayFieldsFactory $factory, string $gateway)
    {
        $params = GatewayInfo::getParameters($gateway) ?? [];
        $secretKey = $params['stripe_secret_key'] ?? null;

        if (!is_string($secretKey) || $secretKey === '') {
            throw new \Exception('Stripe secret key not set');
        }

        $amount = $factory->getPaymentAmount();
        if ($amount === null || $amount <= 0) {
            throw new \Exception('Payment amount not set');
        }

        $currency = strtolower(trim((string) ($factory->getPaymentCurrency() ?? 'usd')));
        if ($currency === '') {
            $currency = 'usd';
        }

        $amountCents = (int) round($amount * 100);
        if ($amountCents < 1) {
            return null;
        }

        return PaymentIntent::create([
            'amount' => $amountCents,
            'currency' => $currency,
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);
    }

    /**
     * Register Stripe.js and the Payment Element bootstrap script for the current response.
     */
    private function requireStripePaymentElementAssets(): void
    {
        Requirements::javascript(self::STRIPE_JS_URL);
        Requirements::javascript('silverstripe/silverstripe-omnipay: client/js/stripe-payment-element.js');
    }
}
