# Configuring Silverstripe Omnipay

You can configure gateway settings in your `mysite/_config/payment.yml` file.
Here you can define a list of allowed gateways, and separately set the gateway-specific settings.

You configure the allowed gateways by setting the `allowed_gateway` config on `Payment`.

To configure the individual gateway parameters, use `SilverStripe\Omnipay\GatewayInfo` and add a key for every Gateway you want to configure.

Each Gateway can have the following settings:

| Setting                  | Type             | Description
| ------------------------ | ---------------- | ---
| `is_manual`              | *boolean*        | Set this to true if this gateway should be considered a "Manual" gateway (eg. Invoice)
| `use_authorize`          | *boolean*        | Whether or not this gateway should prefer authorize over purchase
| `use_async_notification` | *boolean*        | When set to true, this gateway will receive asynchronous notifications from the payment provider to confirm status changes
| `use_static_route`       | *boolean*        | Enables a static route for payment updates. Only use this, if your payment provider does not accept dynamic return urls and needs a single endpoint for server-to-server communication. (Defaults to *false*). [More information](StaticRoutes.md)
| `token_key`              | *string*         | Key for the token parameter (for gateways that generate tokens for credit-cards)
| `required_fields`        | *array*          | An array of required form-fields
| `parameters`             | *map*            | All gateway parameters that will be passed along to the Omnipay Gateway instance
| `is_offsite`             | *boolean*        | You can explicitly mark this gateway as being offsite. Use with caution and only if the system fails to automatically determine this
| `can_capture`            | *boolean/string* | Set how/if authorized payments can be captured. Defaults to "partial". Valid values are "off" or `false` (capturing disabled), "full" (can only capture full amounts), "partial" or `true` (can capture partially) and "multiple" which allows multiple partial captures.
| `can_refund`             | *boolean/string* | Set how/if captured payments can be refunded. Defaults to "partial". Valid values are "off" or `false` (refunding disabled), "full" (can only refund full amounts), "partial" or `true` (can refund partially) and "multiple" which allows multiple partial refunds.
| `can_void`               | *boolean*        | Whether or not voiding of authorized payments should be allowed. Defaults to *true*
| `max_capture`            | *mixed*          | Configuration for excess capturing of authorized amounts. See the **max_capture** section further below.

```yaml
---
Name: payment
---
SilverStripe\Omnipay\Model\Payment:
  allowed_gateways:
    - 'PayPal_Express'
    - 'PaymentExpress_PxPay'
    - 'Manual'

SilverStripe\Omnipay\GatewayInfo:
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
SilverStripe\Omnipay\Model\Payment:
  allowed_gateways:
    - 'Dummy'

SilverStripe\Omnipay\GatewayInfo:
  PayPal_Express:
    parameters:
      testMode: true
---
Only:
  environment: 'live'
---
SilverStripe\Omnipay\GatewayInfo:
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
