# How to set up SilverStripe Omnipay with PayPal Express

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
    file_logging: 1
    allowed_gateways:
        - 'PayPal_Express'
    parameters:
        PayPal_Express:
            username: 'name-facilitator_api1.yourdomain.com'
            password: '327593262995'
            signature: 'ABACEFAFASVAEVAWEVAEVAWEAEDASDFSAFasdf.ASVawevawevasdva'
            testMode: true
```

Don't forgt to add `testMode: true` so that we use the PayPal sandbox.

Flush your config cache by visiting http://yoursite.com/?flush=all

You should now be ready to test payments through PayPal.

# TroubleShooting

If you ever see the message `Security header is not valid`, then this means the API credentials are incorrect. Perhaps you didn't enter the "Classic TEST API credentials"