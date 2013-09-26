# SilverStripe Payments via Omnipay (Work in Progress)

This module is intended to replace the SilverStripe Payment module. It provides a thin wrapping of the PHP Omnipay payments library.

To understand more about omnipay, see: https://github.com/adrianmacneil/omnipay

## Requirements

 * silverstripe framework 3.1+
 * silverstripe cms 3.1+
 * omnipay + it's dependencies - which include guzzle and some symphony libraries

*Note:* Composer is currently the only supported way to set up this module.

## Gateway Features
You should be aware that there are a number of features, but not all gateways support them.

To see what features are supported, visit: `[your-site-url]/dev/payment`.

## Responsibilities

There are three different code bases to consider:

 * Application - your SilverStripe application that you want to include payment in.
 * Payment Module - this module. Handles SilverStripe integration.
 * Omnipay Framework - gateway interaction handling.

Your application is responsible for:
* Configuration of payment gateways, via YAML
* Linking one, or many payments to the thing you want to pay for.

This payment module is responsible for:
* Providing a few models to store payment state, and history in
* Handling responses from external gateways
* Integrating with omnipay

Omnipay will send requests to the gateway servers, and parse their responses in to a fairly consistent format.

## Configuration

You can configure gateway settings in your `mysite/_config/payment.yml` file. Here you can select a list of allowed gateways, and separately set the gateway-specific settings.

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
```

Add the `Payable` DataExtension to the dataobject you want to make payment for.

## Initiating a payment

Inside your controller action:
```php
$payment = Payment::createPayment($amount, $currency, $gateway);
```

You get back a payment dataobject. Payment model at this stage can be thought of as "an intention to pay".
You can then perform some, or all of the following actions on that object:
 * Authorize - get approval from the gateway to charge money to a customer.
 * Capture - do the actual payment. This is the step where money will exchange hands.
 * 

the same controller action continued..
```php
$result = $payment->authorise();

if($result->isSuccess()){
	//success redirect
	$this->redirect($this->Link('confirm'));
	return;
}elseif($result->isRedirect()){
	//redirect to gateway site
	$this->redirect($response->getRedirectUrl());
	return;
}else{
	//failure, go back
	$this->redirectBack();
}
```