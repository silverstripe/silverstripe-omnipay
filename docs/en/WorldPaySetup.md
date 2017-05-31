# Setting Up WorldPay payments

In order to start taking payments with WorldPay you will need to
create an account with [Worldpay](http://www.worldpay.com/).

next you will need to install the Omnipay WorldPay libraries
(ideally via composer):

    # composer require "omnipay/worldpay:~2.0"

## Setup WorldPay on your install

Once you have the WorldPay module installed, add the following
to `mysite/_config/payment.yml`

````
---
Name: payment
---
Payment:
  allowed_gateways:
    - 'WorldPay'

GatewayInfo:
  WorldPay:
    parameters:
      installationId: '1010618'
      callbackPassword: 'xyz'
---
Except:
  environment: 'live'
---
GatewayInfo:
  WorldPay:
    parameters:
      testMode: true
---
Only:
  environment: 'live'
---
GatewayInfo:
  WorldPay:
    parameters:
      callbackPassword: 'abc'
````

The `installationId` will be provided by WorldPay when your account is
setup or can be retrieved from the "Installations" screen of the [WorldPay Admin](https://secure.worldpay.com/sso/public/auth/login.html).

The `callbackPassword` can be set in the WorldPay admin > Installations > Installation Adminiatration (using the 'Payment Response password' field).
**Note** You can add different passwords for test and live environments.

Additional supported settings can be found at the [omnipay-worldpay github page](https://github.com/thephpleague/omnipay-worldpay/blob/master/src/Gateway.php#L21).

## Using a custom callback response

If you want a custom callback response (so the user sees a styled page at
the end of the payment process and is returned back to your site) you
will need to enable the `WorldPayResponseExtension`.

You can add the following to your `config.yml` to achieve this:

````
SilverStripe\Omnipay\Service\PaymentService:
  extensions:
    - WorldPayResponseExtension
````

If you want to customise the apperance of the response page, just copy
the `WorldPayCallback.ss` template from this module and add it to your
`templates` folder.