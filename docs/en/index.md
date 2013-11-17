# Contents

 * [Payment Scenarios](payment-scenarios)
 * [Development Plan](development-plan)
 * [Logging](logging)
 * [Security](security)


## Use cases

Use this module to provide payment for things like:

 * Pay for invoice/bill/order
 * Event registrations
 * Make a donation
 * Renew a subscription
 * Top-up an account
 * Request a payment via email
 * Enter a user's credit card details via phone

## Model

Payable has_many payments. This is an exension that provides a has_many relationship.

Provide functions on Payable: totalPaid, totalOutstanding, totalPending

## Currency handling?

- need use cases
