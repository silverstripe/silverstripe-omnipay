# How to set up SilverStripe Omnipay with PayPal Express

Here is how you configure the omnipay module and work with paypal to test payments. The process is fairly straight forward.
One key thing to note is that we are using PayPal's "Classic API".


Sign up and in to https://developer.paypal.com/

Create an application [here](https://developer.paypal.com/webapps/developer/applications)

Locate your "Classic TEST API credentials".
At the time of writing, this means [here](https://developer.paypal.com/webapps/developer/applications/accounts):

 * Navigating to Applications > Sandbox accounts
 * Click the user in the list
 * Click 'Profile'
 * Naviate to the API credentials tab

This is where you should see your "Classic TEST API credentials", it is a Username, Password, and Signature.

Configure your yaml file:

```yaml
---
Name: payment
---
Payment:
  allowed_gateways:
    - 'PayPal_Express'
GatewayInfo:
  PayPal_Express:
    parameters:
      username: 'name-facilitator_api1.yourdomain.com'
      password: '327593262995'
      signature: 'ABACEFAFASVAEVAWEVAEVAWEAEDASDFSAFasdf.ASVawevawevasdva'
      testMode: true
```

Don't forgt to add `testMode: true` so that we use the PayPal sandbox.

Flush your config cache by visiting http://yoursite.com/?flush=all

You should now be ready to test payments through PayPal.

You'll find test credit card details on the 'Funding' tab of the sandbox account details.

To test failures (negative conditions), follow the [paypal instructions here](https://developer.paypal.com/docs/classic/lifecycle/sb_error-conditions/);

# Going live

Follow [these instructions on PayPal](https://developer.paypal.com/webapps/developer/docs/classic/lifecycle/goingLive/).

Make sure you update your yaml to use the live Classic API credentials.

# TroubleShooting

If you ever see the message `Security header is not valid`, then this means the API credentials are incorrect. Perhaps you didn't enter the correct "Classic TEST API credentials".
