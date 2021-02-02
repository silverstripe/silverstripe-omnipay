# Static Gateway Endpoints

Most payment providers accept dynamic callback URLs where they send status-updates to.
A dynamic callback URL looks like this: `https://example.com/paymentendpoint/c62f90dc62bdbf4966b58e38d6f767/complete`.

It contains the payment identifier and action and will be sent as callback url with the payment request.
This URL is only valid for one payment. Using this kind of callback is the default behavior and you should not use anything else, if not forced to!

In some rare cases, payment providers *only* allow a single callback URL (which is normally being set in the admin interface of the payment provider).
This callback url will receive all responses from that given provider and must therefore be capable of finding and processing the appropriate payment with the given request payload.
An example of such a static callback URL: `https://example.com/paymentendpoint/BarclaysEpdq_Essential/complete`.

In order to make static-routes work for your payment-provider, you need to do the following:

### Enable the static route in your config file

Use the `use_static_route` flag to enable the static route for your gateway. Only enable this for the gateway(s) that actually *need* a static route.

Example:

```env
# E.g. in a .env file
BARCLAYS_CLIENT_ID="xxxxxx"
BARCLAYS_SHA_IN="abc"
```

```yaml
SilverStripe\Omnipay\GatewayInfo:
  BarclaysEpdq_Essential:
    use_static_route: true
    parameters:
      clientId: '`BARCLAYS_CLIENT_ID`'
      shaIn: '`BARCLAYS_SHA_IN`'
```

### Write an Extension that gets a payment from the incoming request data

With static routes we have no payment id to easily look up the payment. You have to implement the lookup yourself.
*If you implement such an extension for a payment provider, please contribute it back to us via pull-request*!

Your extension should implement the following method:

`updatePaymentFromRequest(SS_HTTPRequest $request, $gateway) : Payment`

The `$request` parameter is the incoming HTTP request. You can use this to look up the Payment with the incoming request data.
The `$gateway` parameter is a string and will contain the name of the gateway. Your method **must** check if the gateway name matches and only return a payment for the gateway your extension is responsible for!

Example:

```php
class BarclaysPaymentGatewayControllerExtension extends Extension
{
    /**
     * Update the payment identifier from the gateway.
     * @param SS_HTTPRequest $request
     * @param string $gateway
     * @return Payment|null
     */
    public function updatePaymentFromRequest(SS_HTTPRequest $request, $gateway)
    {
        // Always do this!
        if ($gateway == 'BarclaysEpdq_Essential') {
            // In this example we get an order ID and we use this to look up our payment
            $order = Order::get()->find('OrderNumber', $request->postVar('orderID'));

            if ($order && $order->Payments()->exists()) {
                // return the found payment
                return $order->Payments()->first();
            }
        }
    }
}
```

In the example above, the Order-ID was passed along to the payment provider. The payment provider then calls our
static endpoint with the same order-ID and we look up the order (and the payment) using that information.
What information you receive from the payment provider and how it's being transmitted (could be a request-variable, header or something else)
will vary from one payment provider to the other. That's why you'd have to implement the proper lookup in an extension.
Ideally, you would send the unique payment ID to the payment provider, if – for example – your order could have multiple payments.

Then add the extension to the `PaymentGatewayController`, like so:

```yaml
SilverStripe\Omnipay\PaymentGatewayController:
  extensions:
    - BarclaysPaymentGatewayControllerExtension
```

### (optional) implement an extension hook that returns the payment action

In some rare cases, you won't be able to configure multiple endpoints for different actions (eg. a separate endpoint URL for "complete" or "cancel").
In such a case, your endpoint URL might be: `https://example.com/paymentendpoint/BarclaysEpdq_Essential`

In that case, you'd have to add an extension hook that returns the proper action for the given request.
To do that, implement the `updatePaymentActionFromRequest` method in your extension, which has the following signature:

`updatePaymentActionFromRequest(&$action, Payment $payment, SS_HTTPRequest $request)`

The `$action` parameter should be passed in by reference and you can assign a new value to it.
The `$payment` object will be the payment instance and `$request` the current request.

Always check if `$payment->Gateway` matches your gateway!

Example:

```php
public function updatePaymentActionFromRequest(&$action, Payment $payment, SS_HTTPRequest $request)
{
    // always ensure you're dealing with the correct gateway!
    if ($payment->Gateway == 'BarclaysEpdq_Essential') {
        // In this case, the action is sent as GET variable with the request
        if ($request->getVar('action') {
            $action = $request->getVar('action');
        }
    }
}
```
