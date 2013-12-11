# SilverStripe Payments via Omnipay

[![Build Status](https://api.travis-ci.org/burnbright/silverstripe-omnipay.png)](https://travis-ci.org/burnbright/silverstripe-omnipay)
[![Latest Stable Version](https://poser.pugx.org/burnbright/silverstripe-omnipay/v/stable.png)](https://packagist.org/packages/burnbright/silverstripe-omnipay)
[![Total Downloads](https://poser.pugx.org/burnbright/silverstripe-omnipay/downloads.png)](https://packagist.org/packages/burnbright/silverstripe-omnipay)
[![Latest Unstable Version](https://poser.pugx.org/burnbright/silverstripe-omnipay/v/unstable.png)](https://packagist.org/packages/burnbright/silverstripe-omnipay)

The aim of this module is to make it easy for developers to eaisly integrate the ability to pay for things with their SilverStripe application.
There are many gateway options to choose from, and integrating with additional gateways has a structured approach that should be understandable.
A high quality, simple to use payment module will help to boost the SilverStripe ecosystem, as it allows applications to be profitable.

This module is a complete rewrite of the past Payment module. It is not backwards-compatible.
In a nutshell, it provides a thin wrapping of the PHP Omnipay payments library.
To understand more about omnipay, see: https://github.com/adrianmacneil/omnipay

## Requirements

 * [silverstripe framework](https://github.com/silverstripe/silverstripe-framework) 3.1+
 * [omnipay](https://github.com/omnipay/omnipay) + it's dependencies - which include guzzle and some symphony libraries

*Note:* Composer is currently the only supported way to set up this module.

## Features

 * Gateway configuration via yaml config
 * Payment / transaction model handling
 * Detailed + structured logging in the database
 * Provides necessary form fields
 * Caters for different types of gateways: on-site capturing, off-site capturing, and manual payment
 * Wraps the Omnipay php library
 * Multiple currencies


### Gateway Features

Different gateways have different features. This means you may get a different level of functionality, depending on the gateway you choose.

 * Delayed capturing. This means you can submit payment details for approval in one step (authorize) of your application, and actually capture the money in a secondary step (capture).
 * Enter credit card details on site. Some gateways allow entering credit card details to a form on your website, and other require users to visit another website to enter those details. This is also known as "on site" vs "off site" credit card processing. It is sometimes possible to emulate on site processing using an iframe containing the off-site payment page.

To see what features are supported, visit: `your-site-url/dev/payment`.

## Responsibilities

There are three different code bases to consider:

 * Application - your SilverStripe application that you want to include payment in.
 * Payment Module - this module. Handles SilverStripe integration.
 * Omnipay Framework - gateway interaction handling.

Your application is responsible for:
* Configuration of payment gateways, via YAML.
* Providing system data
    * amount
    * currency
    * return/cancel urls
* Providing customer data (depending on gateway requirements)
    * name
    * address for billing/shipping
    * credit card details
* Linking one, or many payments to the thing you want to pay for.

This payment module is responsible for:
* Providing a few models to store payment state, and history in
* Handling responses from external gateways
* Integrating with omnipay

The omnipay library is responsible sending requests to the gateway servers, and parsing responses in to a consistent format.

## Configuration

You can configure gateway settings in your `mysite/_config/payment.yml` file. Here you can select a list of allowed gateways, and separately set the gateway-specific settings.

You can also choose to enable file logging by setting `file_logging` to 1.

```yaml
---
Name: payment
---
Payment:
    allowed_gateways:
        - 'PayPal_Express'
        - 'PaymentExpress_PxPay'
        - 'Manual'
    parameters:
        PayPal_Express:
            username: 'example.username.test'
            password: 'txjjllae802325'
            signature: 'wk32hkimhacsdfa'
		PaymentExpress_PxPay:
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
---
Only:
    environment: 'live'
---
Payment:
    parameters:
        PayPal_Express:
            username: 'liveexample.test'
            password: 'livepassawe23'
            signature: 'laivfe23235235'
        PaymentExpress_PxPay:
            username: 'LIVEUSER'
            password: 'n23nl2ltwlwjle'
---
```

The [SilverStripe documentation](http://doc.silverstripe.com/framework/en/topics/configuration#setting-configuration-via-yaml-files) explains more about yaml config files.

## Data model

We have left it up to you to decide how payments are linked in with your existing model.

Here are a few ideas:
 * MyObject has_many Payments - allowing for partial payments to be made
 * MyObject has_one Payment
 * ...or you could generate payments and complete them in a stand alone form.

## Available gateways

In your application, you may want to allow users to choose between a few different payment gateways. This can be useful for users if their first attempt is declined.

```php
$gateways = Payment::get_supported_gateways();
```

## Usage: Making a purchase

Using function chaining, we can create and configure a new payment object, and submit a request to the chosen gateway. The response object has a `redirect` function built in that will either redirect the user to the external gaeway site, or to the given return url.

```php
    Payment::create()
        ->init($gateway = "PxPayGateway", $amount = 100, $currency = "NZD")
        ->setReturnUrl($this->Link('complete')."?donation=".$donation->ID)
        ->setCancelUrl($this->Link()."?message=payment cancelled")
        ->purchase($form->getData())
        ->redirect();
```

Of course you don't need to chain all of these functions, as you may want to redirect somewhere else, or do some further setup.

After payment has been made, the user will be redirected to the given return url (or cancel url, if they cancelled).

## Caveats and Troubleshooting

 * Payments have both an ammount and a currency. That means you should check if all currencies match if you will be adding them up.

