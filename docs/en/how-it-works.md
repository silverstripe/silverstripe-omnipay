# How it works

Redirects **back** from the external gateway will first to to `PaymentGatewayController`, and then again redirect the user to your application. The user won't notice the redirect to `PaymentGatewayController`.

## Payment state machine

Here are the possible states a payment can have

 * Created - new payment model
 * Authorized - payment capture has been authorised by gateway
 * Captured - money has been secussfully recieved
 * Completed - completely paid
 * Refund - funds have been returned to payer
 * Voided - (TODO: what does this mean? cancelled? failed?)