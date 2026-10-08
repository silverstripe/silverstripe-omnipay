# Contents

 * Use cases
 * How it works
 * Responsibilities
 * Data Model
 * Gateway Features
 * Logging
 * Payment messages
 * Payment Scenarios
 * Security


## Use cases

Use this module to provide payment for things like:

 * Pay for invoice/bill/order
 * Event registrations
 * Make a donation
 * Renew a subscription
 * Top-up an account
 * Request a payment via email
 * Enter a user's credit card details via phone


## How it works

### Architecture

`Payment` is the main data model. It contains information such as the gateway (being) used to make the payment, the monetary amount (amount + currency), and the status of the payment.

`Payment` has many `Messages` (`PaymentMessage`). Together they form a log of every request, response, error and notification exchanged with the gateway for that payment. See [Payment messages](#payment-messages) below.

### Payment state machine

Here are the possible states a payment can have:

 * `Created` - new payment model
 * `PendingAuthorization` - authorization is pending. This is an intermediate state before the payment will be Authorized either via user that returns from an offsite gateway or when the payment provider notifies about payment-success via asynchronous callback.
 * `Authorized` - amount has been authorised by gateway
 * `PendingPurchase` - purchase is pending. When initiating a purchase, the payment will enter this state, which is an intermediate state before payment will be Captured
 * `PendingCapture` - capture is pending. When initiating capture, the payment will enter this state, which is an intermediate state before payment will be Captured
 * `Captured` - money has been successfully received.
 * `PendingRefund` - refund is pending. This state will only be used when a refund is started and waiting for an asynchronous confirmation from the payment provider.
 * `Refund` - funds have been returned to payer
 * `PendingVoid` - void is pending. This state will only be used when trying to void a payment and waiting for an asynchronous confirmation from the payment provider.
 * `Void` - payment has been cancelled


## Off-site vs On-site differences

On-site gateways require credit card details to be gathered on the application site, and off-site credit card details are gathered on another 3rd party website.

For off-site gateways, redirects **back** from the external gateway will first go to `PaymentGatewayController`, and then again redirect the user to your application. The user won't notice the redirect to `PaymentGatewayController`.

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

The Omnipay library is responsible for sending requests to the gateway servers, and parsing responses in to a consistent format.

## Data model

Developers have flexibility in choosing how `Payment` DataObjects are connected to their model, but it is recommended that you use a has_many relationship so that you can handle partial payments, and also it means that if one payment fails, then another payment can be made via different means.

Your model you connect payments to will generally be something like: `Bill`, `Invoice`, `Order`, `Donation`, `Registration`

An extension (`Payable`) has been written to provide the above functionality.

## Gateway Features

Different gateways have different features. This means you may get a different level of functionality, depending on the gateway you choose.

 * Delayed capturing. This means you can submit payment details for approval in one step (authorize) of your application, and actually capture the money in a secondary step (capture).
 * Enter credit card details on site. Some gateways allow entering credit card details to a form on your website, and other require users to visit another website to enter those details. This is also known as "on site" vs "off site" credit card processing. It is sometimes possible to emulate on site processing using an iframe containing the off-site payment page.

To see what features are supported, for the installed gateways, visit: `your-site-url/dev/payment`.

### Changing parameters for live environment

It is recommended to store your payment credentials in environment variables. These credentials can then be used in config by using backticks (\`) to reference them:

```env
# E.g. in a .env file
PAYPAL_USERNAME="live.user.com"
PAYPAL_PASSWORD="0987654321"
PAYPAL_SIGNATURE="live-signature"
```

```yaml
SilverStripe\Omnipay\GatewayInfo:
  PayPal_Express:
    parameters:
      username: '`PAYPAL_USERNAME`'
      password: '`PAYPAL_PASSWORD`'
      signature: '`PAYPAL_SIGNATURE`'
```

**Note:** backticks can only be used for _values_ (not array keys), and can only be used in the “parameters” array for gateway info.

Using this approach, payment credentials can be unique to each environment and there’s no risk of accidentally committing them to version control.

Some gateways require extra parameters (e.g. "testMode") that you may wish to omit from your environment variables. It is possible to do this entirely in YAML by having separate configuration blocks for test and live modes:

```yaml
SilverStripe\Omnipay\GatewayInfo:
  PayPal_Express:
    use_authorize: true
    token_key: 'token'
    parameters:
      username: 'sandbox.user.com'
      password: '1234567890'
      signature: 'RandomLettersAndNumbers012345'
      testMode: true

---
Only:
  environment: 'live'
---
# Supply different credentials for "live" environment.
SilverStripe\Omnipay\GatewayInfo:
  PayPal_Express:
    parameters:
      username: 'live.user.com'
      password: '0987654321'
      signature: 'live-signature'
      testMode: false # Make sure to override this to false
```

## Logging

This module logs as much information to the database as possible. This includes:

  * State changes
  * Problems / errors
  * Human notes
  * Gateway-specific data
  * Who performed actions / made changes

## Payment messages

Every message is a `SilverStripe\Omnipay\Model\Message\PaymentMessage`, stored in a single `Omnipay_PaymentMessage` table.
The kind of message is stored in the indexed `Type` column:

| Field                      | Contents                                                                   |
|----------------------------|----------------------------------------------------------------------------|
| `Type`                     | Message type, eg. `PurchaseRequest` (see below)                            |
| `Message`                  | Human readable message returned by the gateway, or the error message       |
| `Code`                     | Gateway response or error code                                             |
| `Reference`                | Gateway transaction reference                                              |
| `Gateway`                  | Gateway the payment used                                                   |
| `ClientIp`                 | IP address of the client that triggered the request                        |
| `SuccessURL`, `FailureURL` | Return/cancel URLs (request messages only)                                 |
| `User`                     | Member who was logged in when the message was written                      |

Message types are defined as constants on the service that creates them. Each service also has an
`ERROR_MESSAGE_TYPES` constant listing its error types (`PaymentService::NOTIFICATION_ERROR_MESSAGE_TYPES` for
notifications).

| Type | Constant |
|------|----------|
| `AuthorizeRequest` | `AuthorizeService::MESSAGE_AUTHORIZE_REQUEST` |
| `AuthorizeError` | `AuthorizeService::MESSAGE_AUTHORIZE_ERROR` |
| `AuthorizeRedirectResponse` | `AuthorizeService::MESSAGE_AUTHORIZE_REDIRECT_RESPONSE` |
| `AwaitingAuthorizeResponse` | `AuthorizeService::MESSAGE_AWAITING_AUTHORIZE_RESPONSE` |
| `CompleteAuthorizeRequest` | `AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_REQUEST` |
| `CompleteAuthorizeError` | `AuthorizeService::MESSAGE_COMPLETE_AUTHORIZE_ERROR` |
| `AuthorizedResponse` | `AuthorizeService::MESSAGE_AUTHORIZED_RESPONSE` |
| `CaptureRequest` | `CaptureService::MESSAGE_CAPTURE_REQUEST` |
| `CaptureError` | `CaptureService::MESSAGE_CAPTURE_ERROR` |
| `CapturedResponse` | `CaptureService::MESSAGE_CAPTURED_RESPONSE` |
| `PartiallyCapturedResponse` | `CaptureService::MESSAGE_PARTIALLY_CAPTURED_RESPONSE` |
| `CreateCardRequest` | `CreateCardService::MESSAGE_CREATE_CARD_REQUEST` |
| `CreateCardError` | `CreateCardService::MESSAGE_CREATE_CARD_ERROR` |
| `CreateCardRedirectResponse` | `CreateCardService::MESSAGE_CREATE_CARD_REDIRECT_RESPONSE` |
| `AwaitingCreateCardResponse` | `CreateCardService::MESSAGE_AWAITING_CREATE_CARD_RESPONSE` |
| `CompleteCreateCardRequest` | `CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_REQUEST` |
| `CompleteCreateCardError` | `CreateCardService::MESSAGE_COMPLETE_CREATE_CARD_ERROR` |
| `CreateCardResponse` | `CreateCardService::MESSAGE_CREATE_CARD_RESPONSE` |
| `NotificationError` | `PaymentService::MESSAGE_NOTIFICATION_ERROR` |
| `NotificationSuccessful` | `PaymentService::MESSAGE_NOTIFICATION_SUCCESSFUL` |
| `NotificationPending` | `PaymentService::MESSAGE_NOTIFICATION_PENDING` |
| `PurchaseRequest` | `PurchaseService::MESSAGE_PURCHASE_REQUEST` |
| `PurchaseError` | `PurchaseService::MESSAGE_PURCHASE_ERROR` |
| `PurchaseRedirectResponse` | `PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE` |
| `AwaitingPurchaseResponse` | `PurchaseService::MESSAGE_AWAITING_PURCHASE_RESPONSE` |
| `CompletePurchaseRequest` | `PurchaseService::MESSAGE_COMPLETE_PURCHASE_REQUEST` |
| `CompletePurchaseError` | `PurchaseService::MESSAGE_COMPLETE_PURCHASE_ERROR` |
| `PurchasedResponse` | `PurchaseService::MESSAGE_PURCHASED_RESPONSE` |
| `RefundRequest` | `RefundService::MESSAGE_REFUND_REQUEST` |
| `RefundError` | `RefundService::MESSAGE_REFUND_ERROR` |
| `RefundedResponse` | `RefundService::MESSAGE_REFUNDED_RESPONSE` |
| `PartiallyRefundedResponse` | `RefundService::MESSAGE_PARTIALLY_REFUNDED_RESPONSE` |
| `VoidRequest` | `VoidService::MESSAGE_VOID_REQUEST` |
| `VoidError` | `VoidService::MESSAGE_VOID_ERROR` |
| `VoidedResponse` | `VoidService::MESSAGE_VOIDED_RESPONSE` |

Use the constants rather than string literals when querying messages:

```php
use SilverStripe\Omnipay\Service\PurchaseService;

// All failed purchase attempts for a payment
$errors = $payment->Messages()->filter('Type', PurchaseService::ERROR_MESSAGE_TYPES);

// The most recent successful purchase response
$response = $payment->getLatestMessageOfType(PurchaseService::MESSAGE_PURCHASED_RESPONSE);
```

Message titles in the CMS are translated via `SilverStripe\Omnipay\Model\Message\PaymentMessage.TYPE_<Type>` keys.

Up to 5.x every message type was a separate `DataObject` subclass. If you are upgrading from 5.x or older, see
[Upgrading](Upgrading.md).

## Payment scenarios

Here is how different gateway scenarios can play out:

### On-site 'purchase' gateway:

 * Purchase requested / or request failed
 * Purchase successful / or gateway response failure

### Off-site 'purchase' gateway:

 * Purchase requested / or request failed
 * Purchase request successful / or gateway responds with failure

  ...client now visits external gateway...

 * Complete purchase requested / or request failed (triggered by client return, or by a call from gateway server)
 * Complete purchase successful / or gateway responds with failure

### On-site 'authorize/capture' gateway:
 * Authorization requested / or request failed
 * Authorize successful / or gateway responds with failure

  ...later...

 * Capture requested / or request failed (triggered by system, admin, or user)
 * Capture successful / or gateway responds with failure

### Off-site 'authorize/capture' gateway:

 * Authorization requested / or request failed
 * Authorize successful / or gateway responds with failure

 ...client now visits external gateway...

 * Complete authorize requested / or request failed (triggered by client return, or by a call from gateway server)
 * Complete authorize successful / or gateway responds with failure

 ...later...

 * Capture requested / or request failed (triggered by system, admin, or user)
 * Capture successful / or gateway responds with failure

### 'manual' gateway:

 * Manual payment requested / or request failed

 ... payment is made via bank, cheque, cash etc ...

 * Manual payment completed by admin (or system?) / or fails for some reason?


## Security

Credit card details should never fall into the wrong hands! Be safe.

You should:

 * Become familiar with standard SilverStripe security measures, and implement where possible.
 * Use SSL security whenever there is a payment-related form.
 * Make the PaymentGatewayController use SSL.

### On-site vs Off-site gateways

Offsite gateways are a less risky way to get set up to take payments. This is because the responsibility of taking care of credit card details remains with them, as it is captured on their external web page.
