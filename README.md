# SilverStripe Payments via Omnipay

[![Build Status](https://api.travis-ci.org/silverstripe/silverstripe-omnipay.png)](https://travis-ci.org/silverstripe/silverstripe-omnipay)
[![Code Coverage](https://codecov.io/gh/silverstripe/silverstripe-omnipay/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-omnipay)
[![Latest Stable Version](https://poser.pugx.org/silverstripe/silverstripe-omnipay/v/stable.png)](https://packagist.org/packages/silverstripe/silverstripe-omnipay)
[![Total Downloads](https://poser.pugx.org/silverstripe/silverstripe-omnipay/downloads.png)](https://packagist.org/packages/silverstripe/silverstripe-omnipay)
[![Latest Unstable Version](https://poser.pugx.org/silverstripe/silverstripe-omnipay/v/unstable.png)](https://packagist.org/packages/silverstripe/silverstripe-omnipay)

Live chat: [![Gitter](https://badges.gitter.im/Join Chat.svg)](https://gitter.im/silverstripe/silverstripe-omnipay?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

The aim of this module is to make it easy for developers to add online payments to their SilverStripe application. In a
nutshell, it wraps the PHP Omnipay payments library and provides some additional functionality. To understand more about
omnipay, see: https://github.com/thephpleague/omnipay

## Version

3.x (in Development, `master` branch)

For contributions to 2.x (SS 3.x compatible), please use the `2` branch.

## Requirements

 * [silverstripe framework](https://github.com/silverstripe/silverstripe-framework) 4+
 * [omnipay](https://github.com/omnipay/common) 2.4 + its dependencies - which include guzzle and some symphony libraries.

## Features

 * Gateway configuration via YAML config.
 * Payment / transaction model handling.
 * Detailed + structured logging in the database.
 * Provide visitors with one, or many gateways to choose from.
 * Provides form fields, which can change per-gateway.
 * Caters for different types of gateways: on-site capturing, off-site capturing, and manual payment.
 * Wraps the [Omnipay](https://github.com/thephpleague/omnipay) php library.
 * Multiple currencies.

## Compatible Payment Gateways

There are many [gateways](https://github.com/thephpleague/omnipay#payment-gateways) available, which you can install separately.
Note that currently this module uses version 2.x of the Ominpay library.

Searching packagist is useful: https://packagist.org/search/?q=omnipay

It is not too difficult to write your own gateway integration either, if needed.

## Installation

[Composer](http://doc.silverstripe.org/framework/en/installation/composer) is currently the only supported way to set up this module:

```
composer require silverstripe/silverstripe-omnipay
```

As of version 2.0 this module only requires `omnipay/common` so you will also need to pull in your payment adapter of
choice. Have a look at http://omnipay.thephpleague.com/gateways/official/ where the second column is the package name.

For example, if your site uses PayPal you would also need to run:

```
composer require omnipay/paypal
```

There's also short guide how to enable [manual payments](docs/en/ManualPaymentSetup.md) or [PayPal Express](docs/en/PayPalExpressSetup.md) available.

## Configuration

Silverstripe Omnipay offers a lot of configuration options. A full list
can be found in our [dedicated configuration documentation](docs/en/Configuration.md).

## Gateway naming conventions

The way gateways are named is dictated by the Omnipay module. Since there might be different gateways in one Omnipay-Payment-Driver, we need a way to address these via different names.

The rules are pretty simple: Class names beginning with a namespace marker (`\`) are left intact. Non-namespaced classes are expected to be in the `\Omnipay` namespace. In non-namespaced classes, underscores or slashes (`\`) are used to denote a specific gateway instance.

Examples:

 * `\Custom\Gateway` → `\Custom\Gateway`
 * `\Custom_Gateway` → `\Custom_Gateway`
 * `Stripe` → `\Omnipay\Stripe\Gateway`
 * `PayPal\Express` → `\Omnipay\PayPal\ExpressGateway`
 * `PayPal_Express` → `\Omnipay\PayPal\ExpressGateway`


And another example: [Omnipay PayPal](https://github.com/thephpleague/omnipay-paypal) comes with three different gateway implementations: `ExpressGateway`, `ProGateway` and `RestGateway`. The gateway names for these gateways would be:
`PayPal_Express`, `PayPal_Pro` and `PayPal_Rest`.

Please follow the rules above to choose the correct gateway name in your configuration files.

Throughout the documentation and examples of this module, you'll find the syntax with underscores. It's easier to read and less error-prone (escaping) than the syntax with namespace markers (`\`).


## Usage

We have produced a comprehensive [getting started guide](docs/en/GettingStarted.md) in our documentation pages


### The Payment Services and Service Factory

There are currently five payment services available, which map to methods exposed by Omnipay.
Which one you can use in practice depends on the capabilities of the individual gateway. Some gateways will support all
services, while some support only a few (eg. the "Manual" gateway doesn't support "purchase").

The services are:

 - `PurchaseService` : Directly purchase/capture an amount.
 - `AuthorizeService`: Authorize an amount.
 - `CaptureService`: Capture a previously authorized amount.
 - `RefundService`: Refund a previously captured amount.
 - `VoidService`: Void/Cancel an authorized payment.

Each of these services implements an `initiate` and a `complete` method. The `initiate` method is always required and
initiates a service. Depending on how the gateway handles requests, you might also need the `complete` method.

This is the case with offsite payment forms, where `initiate` will redirect the user to the payment form and once he returns
from the offsite form, `complete` will be called to finalize the payment.

Another (less common) case is, when the payment provider uses asynchronous notifications to confirm changes to payments.

While you can instantiate the services explicitly, the recommended approach is to use the `ServiceFactory`.
The service factory allows easy customization of which classes should be instantiated for which intent. The
service-factory can also automatically return an `AuthorizeService` or `PurchaseService`, depending on what was configured
for the chosen Gateway.

The following constants are available to instantiate Services:

 - `INTENT_PURCHASE` requests a purchase service.
 - `INTENT_AUTHORIZE` requests an authorize service.
 - `INTENT_CAPTURE` requests a capture service.
 - `INTENT_REFUND` requests a refund service.
 - `INTENT_VOID` requests a void service.
 - `INTENT_PAYMENT` returns authorize- or purchase-service, depending on selected Gateway.

In code:

```php
$payment = Payment::create()->init("PxPayGateway", 100, "NZD");

// The service will be a `PurchaseService`
$service = ServiceFactory::create()->getService($payment, ServiceFactory::INTENT_PURCHASE);

// Initiate the payment
$response = $service->initiate($data);
```

### Passing correct data to the purchase function

The omnipay library has a defined set of parameters that need to be passed in. Here is a list of parameters that you
should map your data to:

```
transactionId
firstName
lastName
email
company
billingAddress1
billingAddress2
billingCity
billingPostcode
billingState
billingCountry
billingPhone
shippingAddress1
shippingAddress2
shippingCity
shippingPostcode
shippingState
shippingCountry
shippingPhone
```

**Note:** `transactionId` can be a reference that identifies the thing you are paying for, such as an order reference id.
It usually shows up on bank statements for reconciliation purposes, but ultimately depends how the gateway uses it.

## Security

When customizing the payment flow (e.g. subclassing `PaymentForm` or `OrderProcessor`), please take care to only pass
whitelisted user input to `PurchaseService` and the underlying omnipay gateways. The easiest way to ensure no arbitrary
data can be injected is by using `Form->getData()` rather than acessing `$_REQUEST` directly, since this will only
return you data for fields originally defined in the form.

## Debugging payments

Please read the [logging documentation](docs/en/Logging.md) on how to set up logging.


## Renaming gateways and translation

You can change the front-end visible name of a gateway using the translation system. The gateway name must match what
you entered in the `allowed_gateways` YAML config.

For example, inside `mysite/lang/en.yml`:

```yaml
en:
  Gateway:
    Paystation_Hosted: "Credit Card"
    PayPal_Express: "PayPal"
```

This approach can also be used to provide different translations. For further information about module translations,
please read [docs/en/Translating.md](docs/en/Translating.md)

## Caveats and Troubleshooting

Logs will be saved to `debug.log` in the root of your SilverStripe directory. It's highly recommended that you enable
general error-logging on production environments.


## Further Documentation

https://github.com/silverstripe/silverstripe-omnipay/blob/master/docs/en/index.md
