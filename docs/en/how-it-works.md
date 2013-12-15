# How it works

## Architecture

`Payment` is the main data model. It contains information such as the gateway (being) used to make the payment, the monetary amount (ammount + currency), and the status of the payment.

`Payment` has many `Messages`. These represent all the types of logging / transaction messages associated with a single payment.

See more: [Logging](logging.md)

## Payment state machine

Here are the possible states a payment can have

 * Created - new payment model
 * Authorized - payment capture has been authorised by gateway
 * Captured - money has been secussfully recieved
 * Completed - completely paid
 * Refund - funds have been returned to payer
 * Voided - payment has been cancelled


## Off-site vs On-site differences

On-site gateways require credit card details to be gathered on the application site, and off-site credit card details are gathered on another 3rd party website.

For off-site gateways, redirects **back** from the external gateway will first to to `PaymentGatewayController`, and then again redirect the user to your application. The user won't notice the redirect to `PaymentGatewayController`.