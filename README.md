# SilverStripe Payments via Omnipay (Work in Progress)

This module is intended to replace the SilverStripe Payment module. It provides a thin wrapping of the PHP Omnipay payments library.

To understand more about omnipay, see: https://github.com/adrianmacneil/omnipay

## Requirements

 * silverstripe framework 3.1+
 * silverstripe cms 3.1+
 * omnipay + it's dependencies - which include guzzle and some symphony libraries

*Note:* Composer is currently the only supported way to set up this module.

## Features

 * Gateway configuration via yaml config
 * Payment / transaction model handling

### Gateway Features

Different gateways have different features. This means you may get a different level of functionality, depending on the gateway you choose.

 * Delayed capturing. This means you can submit payment details for approval in one step of your application, and actually capture the money at a later point.
 * Enter credit card details on site. Some gateways allow entering credit card details to a form on your website, and other require users to visit another website to enter those details.

To see what features are supported, visit: `your-site-url/dev/payment`.

## Responsibilities

There are three different code bases to consider:

 * Application - your SilverStripe application that you want to include payment in.
 * Payment Module - this module. Handles SilverStripe integration.
 * Omnipay Framework - gateway interaction handling.

Your application is responsible for:
* Configuration of payment gateways, via YAML.
* Providing system (amount, currency, return urls, etc), and customer data (name, address, credit card details, etc).
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

## Data model

We have left it up to you to decide how payments are linked in with your existing model.

Here are a few ideas:
 * MyObject has_many Payments - allowing for partial paymetns to be made
 * MyObject has_one Payment
 * ...or you could generate payments and complete them in a stand alone form.

## Available methods

```php
$methods = Payment::get_supported_methods();
```

## Initiating a payment

The following code examples are assumed to be executed inside your application's controller action, typically after a form has been submitted.

```php
$payment = Payment::createPayment($amount, $currency, $gateway);
```

You get back a payment dataobject. Payment model at this stage can be thought of as "an intention to pay".
You can then perform some, or all of the following actions on that object:
 * **Authorize** - get approval from the gateway to charge money to a customer.
 * **Capture** - initiate the actual payment. This is the step where money will exchange hands, via the gateway.
 * **Refund** - return funds back to the payee.
 * **Void** - 

To request payment authorisation, you need to pass the following data to the `authorise` function on the payment:
```php
$result = $payment->authorise(array(
    'returnURL' => $this->Link('complete'),
    'cancelURL' => $this->Link()
));
```

You will get back a response object, which is an instance of omnipay's `AbstractResponse` class.
With this response object you can determine whether the result of the authorise request was a success, failure, or now requires you to redirect
the user to the gateway website for further processing.
```php
if($result->isSuccess()){
	// success: redirect
	$this->redirect($this->Link('confirm'));
	return;
}elseif($result->isRedirect()){
	// redirect to gateway site
	$this->redirect($response->getRedirectUrl());
	return;
}else{
	// failure: go back
	$this->redirectBack();
    return;
}
```
Note, this payment module will handle any response data that the gateway sends, and will update the payment/transaction models accordingly.


That concludes the handling of an authorisation request. Next you'll want to handle the actual capturing of the payment.
Capturing payment could be done immediately, or you could wait until a later point (Such as when an item has shipped).

To initiate the capture of a payment, first locate the appropriate payment dataobject, then call the `capture` function on it.
```php
//locate payment
$result = $payment->capture();
```



