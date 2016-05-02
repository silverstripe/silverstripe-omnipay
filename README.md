# SilverStripe Payments via Omnipay

[![Build Status](https://api.travis-ci.org/silverstripe/silverstripe-omnipay.png)](https://travis-ci.org/silverstripe/silverstripe-omnipay)
[![Code Coverage](https://scrutinizer-ci.com/g/silverstripe/silverstripe-omnipay/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-omnipay/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe/silverstripe-omnipay/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe/silverstripe-omnipay/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/silverstripe/silverstripe-omnipay/v/stable.png)](https://packagist.org/packages/silverstripe/silverstripe-omnipay)
[![Total Downloads](https://poser.pugx.org/silverstripe/silverstripe-omnipay/downloads.png)](https://packagist.org/packages/silverstripe/silverstripe-omnipay)
[![Latest Unstable Version](https://poser.pugx.org/silverstripe/silverstripe-omnipay/v/unstable.png)](https://packagist.org/packages/silverstripe/silverstripe-omnipay)

Live chat: [![Gitter](https://badges.gitter.im/Join Chat.svg)](https://gitter.im/silverstripe/silverstripe-omnipay?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

The aim of this module is to make it easy for developers to add online payments to their SilverStripe application. It makes heavy use of the [Omnipay Library](https://github.com/thephpleague/omnipay).
There are many gateway options to choose from, and integrating with additional gateways has a structured approach that should be understandable.

A high quality, simple to use payment module will help to boost the SilverStripe ecosystem, as it allows applications to be profitable.

This module is a complete rewrite of the past Payment module. It is not backwards-compatible, but a migration task is available. In a nutshell, it wraps the PHP Omnipay payments library and provides some additional functionality. To understand more about omnipay, see: https://github.com/thephpleague/omnipay

## Version

2.0

## Requirements

 * [silverstripe framework](https://github.com/silverstripe/silverstripe-framework) 3.1+
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

As of version 2.0 this module only requires `omnipay/common` so you will also need to pull in your payment adapter of choice. Have a look at http://omnipay.thephpleague.com/gateways/official/ where the second column is the package name.

For example, if your site uses PayPal you would also need to run:

```
composer require omnipay/paypal
```

## Configuration

You can configure gateway settings in your `mysite/_config/payment.yml` file.
Here you can define a list of allowed gateways, and separately set the gateway-specific settings.

You configure the allowed gateways by setting the `allowed_gateway` config on `Payment`. You can also choose to enable file logging by setting `file_logging` to 1.

To configure the individual gateway parameters, use `GatewayInfo` and add a key for every Gateway you want to configure.

Each Gateway can have the following settings:

| Setting                  | Type             | Description
| ------------------------ | ---------------- | ---
| `is_manual`              | *boolean*        | Set this to true if this gateway should be considered a "Manual" gateway (eg. Invoice)
| `use_authorize`          | *boolean*        | Whether or not this gateway should prefer authorize over purchase
| `use_async_notification` | *boolean*        | When set to true, this gateway will receive asynchronous notifications from the payment provider to confirm status changes
| `token_key`              | *string*         | Key for the token parameter (for gateways that generate tokens for credit-cards)
| `required_fields`        | *array*          | An array of required form-fields
| `parameters`             | *map*            | All gateway parameters that will be passed along to the Omnipay Gateway instance
| `is_offsite`             | *boolean*        | You can explicitly mark this gateway as being offsite. Use with caution and only if the system fails to automatically determine this
| `can_capture`            | *boolean/string* | Set how/if authorized payments can be captured. Defaults to "partial". Valid values are "off" or `false` (capturing disabled), "full" (can only capture full amounts), "partial" or `true` (can capture partially)
| `can_refund`             | *boolean/string* | Set how/if captured payments can be refunded. Defaults to "partial". Valid values are "off" or `false` (refunding disabled), "full" (can only refund full amounts), "partial" or `true` (can refund partially)
| `can_void`               | *boolean*        | Whether or not voiding of authorized payments should be allowed. Defaults to *true*
| `max_capture`            | *mixed*          | Configuration for excess capturing of authorized amounts. See the **max_capture** section further below.

```yaml
---
Name: payment
---
Payment:
  allowed_gateways:
    - 'PayPal_Express'
    - 'PaymentExpress_PxPay'
    - 'Manual'

GatewayInfo:
  PayPal_Express:
    parameters:
      username: 'example.username.test'
      password: 'txjjllae802325'
      signature: 'wk32hkimhacsdfa'
  PaymentExpress_PxPost:
    use_authorize: true
    parameters:
      username: 'EXAMPLEUSER'
      password: '235llgwxle4tol23l'
---
Except:
  environment: 'live'
---
Payment:
  file_logging: 1
  allowed_gateways:
    - 'Dummy'

GatewayInfo:
  Paypal_Express:
    parameters:
      testMode: true
---
Only:
  environment: 'live'
---
GatewayInfo:
  PayPal_Express:
    parameters:
      username: 'liveexample.test'
      password: 'livepassawe23'
      signature: 'laivfe23235235'
  PaymentExpress_PxPost:
    parameters:
      username: 'LIVEUSER'
      password: 'n23nl2ltwlwjle'
```

The [SilverStripe documentation](https://docs.silverstripe.org/en/3.3/developer_guides/configuration/configuration/) explains more about YAML config files.

### The `max_capture` config setting

Some payment providers allow capturing more funds than the ones that were initially authorized. [PayPal](https://developer.paypal.com/docs/classic/paypal-payments-standard/integration-guide/authcapture/) for example allows capturing up to 115% of the authorized amount, capped at USD $75. Eg. if the authorized amount is USD $100, the merchant is allowed to capture $115 at max. If the authorized amount is USD $1000, the max. amount is $1075.

The `max_capture` setting can be used to configure these scenarios. Here are some examples:

```yaml
GatewayInfo:
  PayPal_Express:
    # configure only a fixed amount. The max. increase for captures is always 80
    max_capture: 80
```

```yaml
GatewayInfo:
  PayPal_Express:
    # configure only a fixed percentage. The max. increase for captures is always 10%
    max_capture: '10%'
```

```yaml
GatewayInfo:
  PayPal_Express:
    # configure both percentage and a fixed max. amount
    max_capture: 
      percent: 15
      amount: 75
```

The example above models the PayPal example with 115%, capped at USD $75. The amount is unitless and uses the currency of the current payment. If you need to specify different amounts per currency, it can be done as follows:

```yaml
GatewayInfo:
  PayPal_Express:
    # configure both percentage and a fixed max. amount per currency
    max_capture: 
      percent: 15
      amount:
        USD: 75
        GBP: 55
        EUR: 70
```

The `Payment` class comes with a helper method `getMaxCaptureAmount` that will calculate the max. amount of money you can capture. With the PayPal example above, we'll get the following:

```php
$payment = Payment::create()->init('PayPal_Express', 121, 'USD');
$payment->Status = 'Authorized'; // must be authorized, otherwise the amount will be 0
// the following will print '139.15'
echo $payment->getMaxCaptureAmount();

$payment = Payment::create()->init('PayPal_Express', 1900, 'USD');
$payment->Status = 'Authorized'; // must be authorized, otherwise the amount will be 0
// the following will print '1975.00'
echo $payment->getMaxCaptureAmount();

```

### Gateway naming conventions

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

### List available gateways

In your application, you may want to allow users to choose between a few different payment gateways. This can be useful for users if their first attempt is declined.

```php
$gateways = GatewayInfo::getSupportedGateways();
```

If no allowed gateways are configured, an Exception will be thrown.

### Get payment form fields

The `GatewayFieldsFactory` helper class enables you to produce a list of appropriately configured form fields for the given gateway.

```php
$factory = new GatewayFieldsFactory($gateway);
$fields = $factory->getFields();
```

If the gateway is off-site, then no credit-card fields will be returned.

Fields have also been appropriately grouped, in case you only want to retrieve the credit card related fields, for example.

Required fields can be configured in the YAML config file, as this information is unfortunately not provided by Omnipay:

```yaml
---
Name: payment
---
Payment:
  allowed_gateways:
    - 'PaymentExpress_PxPost'

GatewayInfo:
  PaymentExpress_PxPost:
    parameters:
      username: 'EXAMPLEUSER'
      password: '235llgwxle4tol23l'
    required_fields:
      - 'issueNumber'
      - 'startMonth'
      - 'startYear'
```

### Make your model Payable

You can optionally add the `Payable` extension to your model (e.g. Order, Subscription, Donation, Registration).
This will add a has_many `Payment` relationship to your model, and provide some additional functions

**NOTE:** You must create the associated has_one relationship on `Payment` yourself. This can be done with an extension or via the YAML config system.
For example, the following extension will be applied to `Payment`:

```php
class ShopPayment extends DataExtension {
    private static $has_one = array(
        'Order' => 'Order'
    );
}
```

Or purely with YAML:

```yaml
Payment:
  has_one:
    Order: Order
```

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

### Make a purchase

Using function chaining, we can create and configure a new payment object, and submit a request to the chosen gateway.
The response object has a `redirectOrRespond` function built in that will either redirect the user to the external gateway site, or to the given return url.

```php
// Create the payment object. We pass the desired success and failure URLs as parameter to the payment
$payment = Payment::create()
    ->init("PxPayGateway", 100, "NZD")
    ->setSuccessUrl($this->Link('complete')."/".$donation->ID)
    ->setFailureUrl($this->Link()."?message=payment cancelled")
    ->write();

$response = ServiceFactory::create()
    ->getService($payment, ServiceFactory::INTENT_PAYMENT)
    ->initiate($form->getData());

return $response->redirectOrRespond();
```

Of course you don't need to chain all of these functions, as you may want to redirect somewhere else, or do some further setup.

After payment has been made, the user will be redirected to the given success url (or failure url, if they cancelled).

### Passing correct data to the purchase function

The omnipay library has a defined set of parameters that need to be passed in. Here is a list of parameters that you should map your data to:

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

### Extension hooks

To call your custom code when a payment was captured, you'll need to
introduce an extension that utilises the `onCaptured` extension hook.

For example:

```php
class ShopPayment extends DataExtension {

    private static $has_one = array(
        'Order' => 'Order'
    );

    public function onCaptured($response){
        $order = $this->owner->Order();
        $order->completePayment($this->owner);
    }

}
```

There are many other extension hooks available, you'll find them documented in [docs/en/ExtensionHooks.md](docs/en/ExtensionHooks.md)

## Security

When customizing the payment flow (e.g. subclassing `PaymentForm` or `OrderProcessor`),
please take care to only pass whitelisted user input to `PurchaseService` and the underlying
omnipay gateways. The easiest way to ensure no arbitrary data can be injected
is by using `Form->getData()` rather than acessing `$_REQUEST` directly,
since this will only return you data for fields originally defined in the form.

## Debugging payments

A useful way to debug payment issues is to enable file logging:

```yaml
---
Name: payment
---
Payment:
  file_logging: true #or use 'verbose' for more detailed output
```

## Renaming gateways and translation

You can change the front-end visible name of a gateway using the translation system. The gateway name must match what you entered in the `allowed_gateways` YAML config.

For example, inside `mysite/lang/en.yml`:

```yaml
en:
  Gateway:
    Paystation_Hosted: "Credit Card"
    PayPal_Express: "PayPal"
```

This approach can also be used to provide different translations.
For further information about module translations, please read [docs/en/Translating.md](docs/en/Translating.md)

## Caveats and Troubleshooting

Logs will be saved to `debug.log` in the root of your SilverStripe directory.

 * Payments have both an amount and a currency. That means you should check if all currencies match if you will be adding them up.

It's highly recommended that you enable general error-logging on production environments.
Example for your `mysite/_config.php`:

```php
// log warnings and errors to file.
// Make sure the logfile is not accessible via HTTP. Best put it in a folder outside of the webroot.
SS_Log::add_writer(new SS_LogFileWriter('/var/log/silverstripe-log.log'), SS_Log::WARN, '<=');
```

## Migrating from Payment module

Before you import your database and do a DB/build, add the following YAML config to your site:

```yaml
---
Name: payment
---
Payment:
  db:
    Status: "Enum('Created,Authorized,Captured,Refunded,Void,Incomplete,Success,Failure,Pending','Created')"
```

This will re-introduce old enumeration values to the DB.

Run the migration task: yoursite.com/dev/tasks/MigratePaymentTask


## Further Documentation

https://github.com/silverstripe/silverstripe-omnipay/blob/master/docs/en/index.md

## Attributions

 - Icons used in Payment Admin are part of the [Silk Icon set 1.3](http://www.famfamfam.com/lab/icons/silk/). [Creative Commons Attribution 2.5 License](http://creativecommons.org/licenses/by/2.5/)
