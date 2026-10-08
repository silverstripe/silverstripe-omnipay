# Stripe Payment Element

The module can render the [Stripe Payment Element](https://docs.stripe.com/payments/payment-element) in place of
the standard credit card fields when you use the Omnipay `Stripe_PaymentIntents` gateway. Card details are entered
in an iframe hosted by Stripe and never reach your server. Your form only receives the ID of a Stripe payment method
(`pm_…`), which is passed to Omnipay to create and confirm a PaymentIntent.

## Installation

Install the Omnipay Stripe gateway:

```bash
composer require omnipay/stripe
```

The module exposes `client/js/stripe-payment-element.js`. Run `composer vendor-expose` if your project doesn't
expose module resources automatically.

## Configuration

Add your Stripe API keys to your environment:

```env
# E.g. in a .env file
STRIPE_SECRET_KEY="sk_test_..."
STRIPE_PUBLISHABLE_KEY="pk_test_..."
```

Then configure the gateway:

```yaml
---
Name: payment
---
SilverStripe\Omnipay\Model\Payment:
  allowed_gateways:
    - 'Stripe_PaymentIntents'

SilverStripe\Omnipay\GatewayInfo:
  Stripe_PaymentIntents:
    token_key: 'paymentMethod'
    parameters:
      # The secret key, used by Omnipay
      apiKey: '`STRIPE_SECRET_KEY`'
      # The publishable key, used by Stripe.js in the browser
      stripe_publishable_key: '`STRIPE_PUBLISHABLE_KEY`'
```

Flush your config cache by visiting http://yoursite.com/?flush=all

`Stripe_PaymentIntents` requires a single `paymentMethod` field (see `GatewayInfo::requiredFields()`); the standard
card fields (`name`, `number`, `expiryMonth`, `expiryYear`, `cvv`) aren't required for this gateway. Add any other
fields you need, such as `email`, to `required_fields` as usual.

The Payment Element also works with gateways that use `Stripe_PaymentIntents` as their `gateway_class`, for example
to accept payments into more than one Stripe account (see [Configuration](Configuration.md)).

## Building the payment form

Use the `GatewayFieldsFactory` as you would for any other gateway, but set the amount and currency of the payment
before calling `getFields()`. The Payment Element uses them to show the payment methods that are available for the
payment, so they should match the amount of the `Payment`.

```php
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceFactory;

class CheckoutController extends Controller
{
    private static $allowed_actions = ['PaymentForm'];

    public function PaymentForm()
    {
        $order = $this->getOrder();

        $factory = GatewayFieldsFactory::create('Stripe_PaymentIntents', ['Card', 'Email'])
            ->setPaymentAmount($order->Total)
            ->setPaymentCurrency('NZD');

        return Form::create(
            $this,
            'PaymentForm',
            $factory->getFields(),
            FieldList::create(FormAction::create('doPay', 'Pay now')),
            $factory->getValidator()
        );
    }

    public function doPay(array $data, Form $form)
    {
        $order = $this->getOrder();

        $payment = Payment::create()
            ->init('Stripe_PaymentIntents', $order->Total, 'NZD')
            ->setSuccessUrl($this->Link('complete'))
            ->setFailureUrl($this->Link('failed'));
        $payment->write();

        $data = GatewayFieldsFactory::create('Stripe_PaymentIntents')->normalizeFormData($form->getData());

        return ServiceFactory::create()
            ->getService($payment, ServiceFactory::INTENT_PAYMENT)
            ->initiate($data)
            ->redirectOrRespond();
    }
}
```

What happens:

1. `getFields()` replaces the card fields with a mount node for the Payment Element and a hidden `paymentMethod`
   field. The mount node carries the publishable key and the options for
   [`stripe.elements()`](https://docs.stripe.com/js/elements_object/create_without_intent): `mode`, `amount`,
   `currency` and `paymentMethodCreation: 'manual'` (plus `captureMethod: 'manual'` for gateways with
   `use_authorize`). Stripe.js and `client/js/stripe-payment-element.js` are added to the page via `Requirements`.
   No request is made to Stripe at this point.
2. The script mounts the Payment Element. Submit buttons of the form stay disabled until the details entered are
   complete.
3. When the form is submitted, the script creates a payment method from the Payment Element, stores its ID in the
   hidden `paymentMethod` field and submits the form. While this happens the form has `aria-busy="true"` and the
   `stripe-payment-element--submitting` class, which you can use to show a loading state.
4. The `PaymentService` passes `paymentMethod` to Omnipay unchanged and sets `confirm: true`, so the PaymentIntent is
   created and confirmed in a single request. Pass `confirm` in the data given to `initiate()` to override this.
   This is the only PaymentIntent for the payment.

With a test publishable key (`pk_test_…`) the [Stripe.js testing assistant](https://docs.stripe.com/sdks/stripejs-testing-assistant)
is enabled, which helps you fill in [test cards](https://docs.stripe.com/testing).

A missing publishable key, amount or currency throws an `InvalidConfigurationException`.

### 3-D Secure and other customer actions

Some payments need further action from the customer, such as 3-D Secure authentication or a bank redirect. In that
case Stripe returns a redirect: the payment becomes `PendingPurchase` (or `PendingAuthorization`), the ID of the
PaymentIntent (`pi_…`) is stored as the payment's `TransactionReference`, and the customer is redirected to Stripe.

When the customer returns, `PaymentGatewayController` completes the payment: the stored PaymentIntent is confirmed
and the payment becomes `Captured` (or `Authorized`). If the customer failed authentication, the payment stays
pending and an error message is logged. The PaymentIntent is always taken from the payment, never from request data.

## Customising the Payment Element

The mount node and the [Appearance API](https://docs.stripe.com/elements/appearance-api) options can be configured:

```yaml
SilverStripe\Omnipay\Stripe\StripeGatewayFieldsProvider:
  stripe_payment_element_mount_id: 'payment-element'
  stripe_payment_element_mount_extra_classes: 'checkout__payment'
  stripe_payment_element_appearance:
    theme: 'stripe'
    variables:
      colorPrimary: '#0570de'
```

The hidden field can be renamed like any other field with the `GatewayFieldsFactory` `rename` config. Use
`normalizeFormData()` (as in the example above) to map it back to `paymentMethod`.

Two extension hooks on `GatewayFieldsFactory` let you change the fields and the options of the Payment Element, see
[Extension hooks](ExtensionHooks.md#gatewayfieldsfactory):

```php
use SilverStripe\Core\Extension;

class StripePaymentElementExtension extends Extension
{
    public function updateStripePaymentElementOptions(array &$options, string $gateway): void
    {
        // Only offer card payments
        $options['paymentMethodTypes'] = ['card'];
    }
}
```

```yaml
SilverStripe\Omnipay\GatewayFieldsFactory:
  extensions:
    - StripePaymentElementExtension
```

Options that restrict the payment methods (such as `paymentMethodTypes`) must also be accepted by the PaymentIntent
that Omnipay creates. Use the `onBeforePurchase` / `onBeforeAuthorize` [extension hooks](ExtensionHooks.md) to change
the data sent to Omnipay, eg. to add a `description` or `metadata` to the PaymentIntent.

## Using a different gateway fields provider

The Payment Element is implemented as a `GatewayFieldsProvider`, which replaces the `Card` field group of the
`GatewayFieldsFactory` and the required card fields of a gateway. You can write your own provider for other gateways
that collect card details client-side, and map it to the gateway:

```yaml
SilverStripe\Omnipay\GatewayFieldsFactory:
  gateway_fields_providers:
    Braintree: App\Payment\BraintreeDropInFieldsProvider
```

Keys can be gateway names, Omnipay short names or Omnipay class names. Gateways with a `gateway_class` use the
provider configured for that class, unless they have an entry of their own. Set an entry to `null` to use the
standard card fields for a gateway:

```yaml
SilverStripe\Omnipay\GatewayFieldsFactory:
  gateway_fields_providers:
    Stripe_PaymentIntents: null
```
