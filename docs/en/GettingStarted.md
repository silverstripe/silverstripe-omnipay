# Getting Started with Silverstripe OmniPay

Once you have downloaded the module, you will need to get it setup
and integrate it into your application.

**NOTE** You will need some experience with writing Silverstripe Forms,
custom Controllers and Extensions in order to make use of this module.
If you need more information then please visit the following:

* https://docs.silverstripe.org/en/4/developer_guides/forms/
* https://docs.silverstripe.org/en/4/developer_guides/controllers/
* https://docs.silverstripe.org/en/4/developer_guides/extending/extensions/

The basic process you will need to follow will involve the following
steps:

* Get the user to select the prefered payment gateway
* Generate a payment form that will contain this modules scaffolded form fields.
* Add a function to your form or controller to process the submitted form and redirect as required.
* *If Required* Add an extension to the Payment class to handle payment responses (such as Captured, Failed, ETC).
* *If Required* Make a model (EG an Order) "Payable" (allowing it to be marked as paid).

**NOTE** You can also use this module to make more direct payments (for example if you have saved the users card details and want to perform micro transactions). For more info on direct payments, skip to the end.

## Get user to select their prefered gateway

If your application supports payment via multiple payment gateways
(for example PayPal and WorldPay), you may wish for the user to select
which gateway they would prefer to use, or change if their first attempt
is declined.

You can get a list of supported payment gateways with:

```php
$gateways = GatewayInfo::getSupportedGateways();
```

If you wanted to use a form that allowed a user to select their
prefered payment gateway then you would generate something like the below:

```php
$form = Form::create(
    "Form",
    $controller,
    FieldList::create(OptionsetField::create(
        'PaymentMethod',
        'Please choose how you would like to pay',
        GatewayInfo::getSupportedGateways()
    )),
    FieldList::create(FormAction::create(
        'doSubmit',
        'Enter Payment Details'
    ))
)
```

You would then need to add a `doSubmit` function that processes the form
and redirects to your payment.

**NOTE** If no allowed gateways are configured, an Exception will be thrown.

## Generating a payment form

In order to make a payment, you need to get the required info and then either
redirect the user to the payment gateway or send the payment details to the
provider directly. Thankfully omnipay takes away a lot of the grunt work of
doing this, but we still have to do a little work.

The `GatewayFieldsFactory` helper class enables you to produce a list of appropriately configured form fields for the given gateway.

```php
$factory = new GatewayFieldsFactory($gateway);
$fields = $factory->getFields();
```

If the gateway is off-site, then no credit-card fields will be returned.

A more complete example would be:

```php
use SilverStripe\Omnipay\GatewayFieldsFactory;

class PaymentController extends Controller
{
    public function Form()
    {
        $factory = GatewayFieldsFactory::create($gateway);

        return Form::create(
            $this,
            "Form",
            $factory->getFields(),
            FieldList::create(FormAction::create(
                "doSubmit",
                _t("Checkout.PayNow", "Pay Now")
            ))
        );
    }
}
```

Fields have been appropriately grouped, in case you only want to retrieve the credit card related fields, for example.

### Required Fields

Required fields can be configured in the YAML config file, as this information is unfortunately not provided by Omnipay:

```env
# E.g. in a .env file
PXPOST_USERNAME="EXAMPLEUSER"
PXPOST_PASSWORD="235llgwxle4tol23l"
```

```yaml
---
Name: payment
---
SilverStripe\Omnipay\Model\Payment:
  allowed_gateways:
    - 'PaymentExpress_PxPost'

SilverStripe\Omnipay\GatewayInfo:
  PaymentExpress_PxPost:
    parameters:
      username: '`PXPOST_USERNAME`'
      password: '`PXPOST_PASSWORD`'
    required_fields:
      - 'issueNumber'
      - 'startMonth'
      - 'startYear'
```

## Process Payment Form/Make a purchase

Once we have submitted the payment form, we need to process the data and
submit it to the payment gateway. Depending on the gateway, this could mean
redirecting to another site, or submiting payment data to their API.

Using function chaining, we can create and configure a new payment object, and submit a request to the chosen gateway.

The response object has a `redirectOrRespond` function built in that will either redirect the user to the external gateway site, or to the given return url.

An example (following on from the form above would be)

```php
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Model\Payment;

class Payment_Controller extends Controller
{
    ...

    public function doSubmit($data,$form)
    {
        // Create the payment object. We pass the desired success
        // and failure URLs as parameter to the payment
        $payment = Payment::create()
            ->init("PxPayGateway", 100, "NZD")
            ->setSuccessUrl($this->Link('complete')."/".$donation->ID)
            ->setFailureUrl($this->Link()."?message=payment cancelled");

        // Save it to the database to generate an ID
        $payment->write();

        $response = ServiceFactory::create()
            ->getService($payment, ServiceFactory::INTENT_PAYMENT)
            ->initiate($data);

        return $response->redirectOrRespond();
    }
}
```

Of course you don't need to chain all of these functions, as you may want to redirect somewhere else, or do some further setup.

After payment has been made, the user will be redirected to the given success url (or failure url, if they cancelled).

## Extend Payment to capture payment responses

Once a payment has been processed by a payment gateway, you may want
your application to perform certain tasks (based on the response).

To do this, you'll need to introduce an extension that utilises the relevent extension hook (for example `onCaptured`).

For example:

```php
class ShopPayment extends DataExtension {

    private static $has_one = array(
        'Order' => 'Order'
    );

    public function onCaptured($response){
        $order = $this->owner->Order();
        $order->completePayment($this->owner);
    }

}
```

There are many other extension hooks available, you'll find them documented in [docs/en/ExtensionHooks.md](ExtensionHooks.md)

## Make your model Payable

You can optionally add the `Payable` extension to your model (e.g. Order, Subscription, Donation, Registration).
This will add a has_many `Payment` relationship to your model, and provide some additional functions

**NOTE:** You must create the associated has_one relationship on `Payment` yourself. This can be done with an extension or via the YAML config system.
For example, the following extension will be applied to `Payment`:

```php
class ShopPayment extends DataExtension {
    private static $has_one = array(
        'Order' => 'Order'
    );
}
```

Or purely with YAML:

```yaml
Payment:
  has_one:
    Order: Order
```

# Making direct payments

Sometimes you will have an application that has stored payment details
in some way and you will want to allow for quicker purchases (for example
micro transactions).

In this case, you will most likely only be using a single payment gateway
(EG Stripe) so you can skip a lot of the steps above.

If you are doing this then you will still need to make use of the `Payment` and `ServiceFactory` classes as above, but you may just call them directly,
for example:

```php
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Model\Payment;

class Payment_Controller extends Controller
{
    private static $allowed_actions = array(
        "takepayment"
    );

    public function takepayment($data,$form)
    {
        // Create the payment object. We pass the desired success
        // and failure URLs as parameter to the payment
        $payment = Payment::create()
            ->init("PxPayGateway", 100, "NZD")
            ->setSuccessUrl($this->Link('complete')."/".$donation->ID)
            ->setFailureUrl($this->Link()."?message=payment cancelled");

        // Save it to the database to generate an ID
        $payment->write();

        $response = ServiceFactory::create()
            ->getService($payment, ServiceFactory::INTENT_PAYMENT)
            ->initiate(array(
                "Field1" => $some_data,
                "Field2" => $some_other_data
            ));

        return $response->redirectOrRespond();
    }
}
```

**NOTE** In order for this to work you would need to provide the fields
required by your payment gateway manually.
