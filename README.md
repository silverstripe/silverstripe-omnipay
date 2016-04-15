# SilverStripe Payments via Omnipay

[![Build Status](https://api.travis-ci.org/burnbright/silverstripe-omnipay.png)](https://travis-ci.org/burnbright/silverstripe-omnipay)
[![Code Coverage](https://scrutinizer-ci.com/g/burnbright/silverstripe-omnipay/badges/coverage.png?s=90fe071f1fec0564c6ee8db6678a73ae5aca9207)](https://scrutinizer-ci.com/g/burnbright/silverstripe-omnipay/)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/burnbright/silverstripe-omnipay/badges/quality-score.png?s=35408c4d68a36e35d0bc2b4b012bd8fb2c6d4d49)](https://scrutinizer-ci.com/g/burnbright/silverstripe-omnipay/)
[![Latest Stable Version](https://poser.pugx.org/burnbright/silverstripe-omnipay/v/stable.png)](https://packagist.org/packages/burnbright/silverstripe-omnipay)
[![Total Downloads](https://poser.pugx.org/burnbright/silverstripe-omnipay/downloads.png)](https://packagist.org/packages/burnbright/silverstripe-omnipay)
[![Latest Unstable Version](https://poser.pugx.org/burnbright/silverstripe-omnipay/v/unstable.png)](https://packagist.org/packages/burnbright/silverstripe-omnipay)

Live chat: [![Gitter](https://badges.gitter.im/Join Chat.svg)](https://gitter.im/burnbright/silverstripe-omnipay?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

The aim of this module is to make it easy for developers to add online payments to their SilverStripe application. It makes heavy use of the [Omnipay Library](https://github.com/thephpleague/omnipay).
There are many gateway options to choose from, and integrating with additional gateways has a structured approach that should be understandable.

A high quality, simple to use payment module will help to boost the SilverStripe ecosystem, as it allows applications to be profitable.

This module is a complete rewrite of the past Payment module. It is not backwards-compatible, but a migration task is available. In a nutshell, it wraps the PHP Omnipay payments library and provides some additional functionality. To understand more about omnipay, see: https://github.com/thephpleague/omnipay

## Version

2.0

## Requirements

 * [silverstripe framework](https://github.com/silverstripe/silverstripe-framework) 3.1+
 * [omnipay](https://github.com/omnipay/common) 2.4 + it's dependencies - which include guzzle and some symphony libraries.

## Features

 * Gateway configuration via yaml config.
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

[Composer](http://doc.silverstripe.org/framework/en/installation/composer) is currently the only supported way to set
up this module:

```
composer require burnbright/silverstripe-omnipay
```

As of version 2.0 this module only requires omnipay/common so you will also need to pull in your payment adapter of
choice. Have a look at http://omnipay.thephpleague.com/gateways/official/ where the second column is the package name.
For example, if you site uses PayPal you would also need to run:

```
composer require omnipay/paypal
```

## Configuration

You can configure gateway settings in your `mysite/_config/payment.yml` file.
Here you can select a list of allowed gateways, and separately set the gateway-specific settings.

You configure the allowed gateways by setting the `allowed_gateway` config on `Payment`. You can also choose to enable file logging by setting `file_logging` to 1.

To configure the individual Gateway parameters, use `GatewayInfo` and add a key for every Gateway you want to configure.

Each Gateway can have the following settings:

| Setting                  | Type      | Description
| ------------------------ | --------- | ---
| `is_manual`              | *boolean* | Set this to true if this Gateway should be considered a "Manual" Payment (eg. Invoice)
| `use_authorize`          | *boolean* | Whether or not this Gateway should prefer authorize over purchase
| `use_async_notification` | *boolean* | When set to true, this Gateway will receive asynchronous notifications from the Payment provider
| `token_key`              | *string*  | Key for the token parameter
| `required_fields`        | *array*   | An array of required form-fields
| `parameters`             | *map*     | All gateway parameters that will be passed along to the Omnipay Gateway instance
| `is_offsite`             | *boolean* | You can explicitly mark this Gateway as being offsite. Use with caution and only if the system fails to automatically determine this.
| `allow_capture`          | *boolean* | Whether or not capturing of authorized payments should be allowed. Defaults to true. Some payment providers capture payment automatically after some period of time, or the person using the CMS should not be allowed to capture payments. You can then disable this feature.
| `allow_refund`           | *boolean* | Whether or not refunding of captured payments should be allowed. Defaults to true.
| `allow_void`             | *boolean* | Whether or not voiding of authorized payments should be allowed. Defaults to true.

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

The [SilverStripe documentation](http://doc.silverstripe.com/framework/en/topics/configuration#setting-configuration-via-yaml-files) explains more about yaml config files.

## Usage

### List available gateways

In your application, you may want to allow users to choose between a few different payment gateways. This can be useful for users if their first attempt is declined.

```php
$gateways = GatewayInfo::getSupportedGateways();
```

If no allowed gateways are configured, then the module will default to providing
the "Manual" gateway.

### Get payment form fields

The `GatewayFieldsFactory` helper class enables you to produce a list of appropriately configured form fields for the given gateway.

```php
$factory = new GatewayFieldsFactory($gateway);
$fields = $factory->getFields();
```

If the gateway is off-site, then no credit-card fields will be returned.

Fields have also been appropriately grouped, incase you only want to retrieve the credit card related fields, for example.

Required fields can be configured in the yaml config file, as this information is unfortunately not provided by Omnipay:

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
**NOTE:** You must create the associated has_one relationship on `Payment` yourself. This can be done with an extension or via the yaml config system.
For example, the following extension will be applied to `Payment`:

```php
class ShopPayment extends DataExtension {
    private static $has_one = array(
        'Order' => 'Order'
    );
}
```

With yaml:

```yaml
Payment:
  has_one:
    Order: Order
```

### The Payment Services and Service Factory

There are currently five payment services available, which map to methods exposed by Omnipay.
Which one you can use in practice depends on the capabilities of the individual Gateway. Some Gateways will support all
services, while some support only a few (eg. the "Manual" Gateway doesn't support "purchase").

The services are:

 - `PurchaseService` : Directly purchase/capture an amount.
 - `AuthorizeService`: Authorize a payment amount.
 - `CaptureService`: Capture a previously authorized amount.
 - `RefundService`: Refund a previously captured amount.
 - `VoidService`: Void/Cancel this payment.

Each of these services implements a `initiate` and a `complete` method. The `initiate` method is always required and
initiates a service. Depending on how the Gateway handles requests, you might also need the `complete` method.

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
// create the payment object
$payment = Payment::create()->init("PxPayGateway", 100, "NZD")->write();

$response = ServiceFactory::create()->getService($payment, ServiceFactory::INTENT_PAYMENT)
    ->setReturnUrl($this->Link('complete')."/".$donation->ID)
    ->setCancelUrl($this->Link()."?message=payment cancelled")
    ->initiate($form->getData());

return $response->redirectOrRespond();
```

Of course you don't need to chain all of these functions, as you may want to redirect somewhere else, or do some further setup.

After payment has been made, the user will be redirected to the given return url (or cancel url, if they cancelled).

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

**Note:** `transactionId` can be a reference that identifies the thing you are paying for, such as an order reference id. It usually shows up on bank statements for reconciliation purposes, but ultimately depends how the gateway uses it.

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

You can change the front-end visible name of a gateway using the translation system. The gateway name must match what you entered in the `allowed_gateways` yaml config.

For example, inside mysite/lang/en.yml:

```yaml
en:
  Payment:
    Paystation_Hosted: "Credit Card"
    PayPal_Express: "PayPal"
```

This approach can also be used to provide different translations.

## Caveats and Troubleshooting

Logs will be saved to `debug.log` in the root of your SilverStripe directory.

 * Payments have both an amount and a currency. That means you should check if all currencies match if you will be adding them up.

## Migrating from Payment module

Before you import your database and do a DB/build, add the following yaml config to your site:

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

https://github.com/burnbright/silverstripe-omnipay/blob/master/docs/en/index.md

## Attributions

 - Icons used in Payment Admin are part of the [Silk Icon set 1.3](http://www.famfamfam.com/lab/icons/silk/). [Creative Commons Attribution 2.5 License](http://creativecommons.org/licenses/by/2.5/)
