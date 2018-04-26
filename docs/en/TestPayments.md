# Testing the payment process

Before putting a site live, it is wise to test payment gateways first (to ensure the payment process behaves as expected).
You can do this in two different ways:

1. Using the `omnipay/dummy` gateway.
2. Using `testMode: true` for your selected payment gateway.

## The `omnipay/dummy` gateway

**NOTE: This module is for testing only, do not use on a live site**

[Omnipay dummy](https://github.com/thephpleague/omnipay-dummy) is a demo payment adaptor for omnipay, allowing you to test the
payment process without signing up for an existing payment provider. This payment provider generates a payment form with standard
card fields and will either complete or fail an order based on the provided card details.

To start using `omnipay/dummy` ensure you add it via composer:

    composer require --dev omnipay/dummy:^2.0

**NOTE: This will install the dummy gateway as a dev dependency, if you do not use dev dependencies in your project, remove `--dev`**

Next you will need to configure the dummy gateway to work on dev and ensure it loads the required fields:

```yml
---
Name: payment
---
# Your main payment config
---
Except:
  environment: 'live'
---
SilverStripe\Omnipay\Model\Payment:
  allowed_gateways:
    - 'Dummy'
SilverStripe\Omnipay\GatewayInfo:
  Dummy:
    required_fields:
      - 'name'
      - 'number'
      - 'expiryMonth'
      - 'expiryYear'
      - 'cvv'
```

## Using `testMode` on your payment gateway

**NOTE: Most gateways will require you to host your site on a server that will allow the gateway to send HTTP requests to it.**

Most payment gateways will support the `testMode` config variable, which when set will use the relevent gateway's sandbox
gateway.

Using `testMode` will allow you to directly test integration with your chosen payment gateway. You can configure it using config,
for example (using paypal)

```yml
---
Name: payment
---
## Example PayPal_Express
# Add your standard paypal config
---
Except:
  environment: 'live'
---
# Change PayPal_Express to your chosen gateway
SilverStripe\Omnipay\GatewayInfo:
  PayPal_Express:
    parameters:
      testMode: true
```
