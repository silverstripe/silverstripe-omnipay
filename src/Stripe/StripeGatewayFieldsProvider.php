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
use SilverStripe\Omnipay\Helper\Logging;
use SilverStripe\Omnipay\Helper\PaymentMoney;
use SilverStripe\View\Requirements;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * Stripe Payment Element fields for {@link \Omnipay\Stripe\PaymentIntentsGateway} (`Stripe_PaymentIntents`).
 *
 * Replaces the credit card fields of {@link GatewayFieldsFactory} with a mount node for the
 * {@link https://docs.stripe.com/payments/payment-element Stripe Payment Element} and a hidden `paymentMethod`
 * field. When the form is submitted, `client/js/stripe-payment-element.js` creates a payment method from the
 * Payment Element and stores its ID (`pm_…`) in the hidden field, which is then passed to Omnipay.
 *
 * Requires `omnipay/stripe` and `stripe/stripe-php`. The gateway is configured in {@link GatewayInfo}:
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
 * before calling {@link GatewayFieldsFactory::getFields()}; they're used to create the PaymentIntent that
 * initialises the Payment Element.
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
     * @throws InvalidConfigurationException when keys, amount or currency are missing
     */
    public function getCardFields(GatewayFieldsFactory $factory): FieldList
    {
        $gateway = (string) $factory->getGateway();

        $publishableKey = $this->getPublishableKey($gateway);
        $secretKey = $this->getSecretKey($gateway);

        try {
            $paymentIntent = $this->createPaymentIntent(
                $this->getPaymentIntentParameters($factory, $gateway),
                $secretKey
            );
        } catch (ApiErrorException $e) {
            if ($logger = Logging::getExceptionLogger()) {
                $logger->error('Stripe PaymentIntent could not be created: ' . $e->getMessage(), [
                    'exception' => $e,
                    'gateway' => $gateway,
                ]);
            }
            $paymentIntent = null;
        }

        $clientSecret = $paymentIntent?->client_secret;
        if (!is_string($clientSecret) || $clientSecret === '') {
            return FieldList::create(
                LiteralField::create('StripePaymentElementMount', sprintf(
                    '<p class="message bad">%s</p>',
                    Convert::raw2xml(_t(
                        GatewayFieldsFactory::class . '.StripePaymentElementUnavailable',
                        'Payment details can\'t be entered at the moment. Please try again later.'
                    ))
                ))
            );
        }

        $this->requireStripePaymentElementAssets();

        $mountId = self::config()->get('stripe_payment_element_mount_id');
        $mountId = is_string($mountId) && $mountId !== '' ? $mountId : 'stripe-payment-element';

        $extraClasses = trim((string) self::config()->get('stripe_payment_element_mount_extra_classes'));

        $appearance = self::config()->get('stripe_payment_element_appearance');
        // The Appearance API expects a JSON object; an empty PHP array would be encoded as a JS array.
        $appearanceJson = is_array($appearance) && $appearance !== []
            ? json_encode($appearance, JSON_THROW_ON_ERROR)
            : '{}';

        $html = sprintf(
            '<div class="%s" id="%s" data-stripe-payment-element="1" data-appearance="%s"'
            . ' data-publishable-key="%s" data-client-secret="%s" aria-live="polite"></div>',
            Convert::raw2att(trim('stripe-payment-element__mount ' . $extraClasses)),
            Convert::raw2att($mountId),
            Convert::raw2att($appearanceJson),
            Convert::raw2att($publishableKey),
            Convert::raw2att($clientSecret)
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
     * Parameters for the PaymentIntent that initialises the Payment Element.
     *
     * @return array<string, mixed>
     * @throws InvalidConfigurationException when amount or currency aren't set
     */
    public function getPaymentIntentParameters(GatewayFieldsFactory $factory, string $gateway): array
    {
        $amount = $factory->getPaymentAmount();
        $currency = strtoupper(trim((string) $factory->getPaymentCurrency()));

        if ($amount === null || $amount <= 0 || $currency === '') {
            throw new InvalidConfigurationException(
                'The Stripe Payment Element requires a payment amount and currency. Call setPaymentAmount() and '
                . 'setPaymentCurrency() on the GatewayFieldsFactory before getFields().'
            );
        }

        $params = [
            // Stripe expects the amount in the smallest currency unit (eg. cents, or yen for JPY)
            'amount' => (int) PaymentMoney::toMoney($amount, $currency)->getAmount(),
            'currency' => strtolower($currency),
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ];

        $factory->extend('updateStripePaymentIntentParameters', $params, $gateway);

        return $params;
    }

    /**
     * Create the PaymentIntent with the Stripe API.
     *
     * @param array<string, mixed> $params
     * @throws ApiErrorException
     */
    protected function createPaymentIntent(array $params, string $secretKey): ?PaymentIntent
    {
        return $this->createStripeClient($secretKey)->paymentIntents->create($params);
    }

    /**
     * @throws InvalidConfigurationException when stripe/stripe-php isn't installed
     */
    protected function createStripeClient(string $secretKey): StripeClient
    {
        if (!class_exists(StripeClient::class)) {
            throw new InvalidConfigurationException(
                'The Stripe Payment Element requires stripe/stripe-php. Install it with composer.'
            );
        }

        return new StripeClient($secretKey);
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
     * The secret key is the `apiKey` parameter that's also used by Omnipay.
     *
     * @throws InvalidConfigurationException when the secret key isn't configured
     */
    protected function getSecretKey(string $gateway): string
    {
        $params = GatewayInfo::getParameters($gateway) ?? [];
        $key = $params['apiKey'] ?? null;

        if (!is_string($key) || $key === '') {
            throw new InvalidConfigurationException(sprintf(
                'Gateway "%s" requires the "apiKey" parameter for the Stripe Payment Element.',
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
