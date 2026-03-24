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
use SilverStripe\View\Requirements;

/**
 * Stripe Payment Element fields for {@link \Omnipay\Stripe\PaymentIntentsGateway}.
 *
 * Map this provider to `Stripe_PaymentIntents` in {@link GatewayFieldsFactory} configuration and use
 * {@link GatewayFieldsFactory} with that gateway (Omnipay short name or class name).
 *
 * This class registers Stripe.js ({@link https://docs.stripe.com/js}) and, when both a publishable
 * key and a PaymentIntent `client_secret` are available on the mount node, mounts the Payment
 * Element and fills the hidden `paymentMethod` field on submit.
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
 * Supply `data-client-secret` on the mount element (e.g. via `updateStripePaymentElementFields`)
 * with the PaymentIntent client secret for the current payment.
 */
class StripeGatewayFieldsProvider implements GatewayFieldsProvider
{
    use Configurable;

    /**
     * Stripe.js v3 bundle URL (see Stripe docs).
     */
    private const STRIPE_JS_URL = 'https://js.stripe.com/v3/';

    /**
     * @config Stripe publishable key (`pk_live_...` / `pk_test_...`) for Stripe.js on the client.
     */
    private static string $stripe_publishable_key = '';

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
        $isPaymentIntentsGateway = $gateway === self::PAYMENT_INTENTS_SHORT_NAME
            || $gateway === self::PAYMENT_INTENTS_GATEWAY_CLASS;

        return $isPaymentIntentsGateway;
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
        $this->requireStripePaymentElementAssets();

        $mountId = self::config()->get('stripe_payment_element_mount_id') ?? 'stripe-payment-element';
        $mountId = is_string($mountId) ? $mountId : 'stripe-payment-element';

        $extraClasses = trim((string) (self::config()->get('stripe_payment_element_mount_extra_classes') ?? ''));

        $appearance = (array) (self::config()->get('stripe_payment_element_appearance') ?? []);

        $appearanceJson = json_encode($appearance, JSON_THROW_ON_ERROR);
        $appearanceAttr = Convert::raw2att($appearanceJson);

        $containerClasses = trim('stripe-payment-element__mount ' . $extraClasses);

        $publishableKey = trim((string) (self::config()->get('stripe_publishable_key') ?? ''));
        $publishableAttr = $publishableKey !== ''
            ? sprintf(' data-publishable-key="%s"', Convert::raw2att($publishableKey))
            : '';

        $html = sprintf(
            '<div class="%s" id="%s" data-stripe-payment-element="1" data-appearance="%s"%s aria-live="polite"></div>',
            Convert::raw2att($containerClasses),
            Convert::raw2att($mountId),
            $appearanceAttr,
            $publishableAttr
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
                $hidden
            )->addExtraClass('stripe-payment-element'),
        ]);

        $gateway = $factory->getGateway();
        $factory->extend('updateStripePaymentElementFields', $fields, $gateway);

        return $fields;
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
