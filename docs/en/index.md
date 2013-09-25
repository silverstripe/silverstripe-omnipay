# Development Planning


## Goals of payment module

The high level goals are to make it easy to:

 * recieve payments via various payment gateways
 * create new gateways

### In scope features

 * Gateway connectivity (omnipay)
 * Provide multiple payment gateway options
 * Configure gateways via yml
 * CreditCard data encapsulation (omnipay)
 * Store payment record in db
 * Payment logging
 * Unit tested (including mock data)
 * Error handling
 	* Configuration / connection issues
	* Retry failed payments using alternative methods
 * Translations

### Not sure if in scope

 * Token billing - token that represents a credit card, for delayed, or reocurring payments.
 * Provide forms?

### Out of scope features

 * Subscripting / reoccurring billing
 * Credit notes

## Use cases

Use this module to provide payment for things like:

 * Orders
 * Event registrations
 * Donations


## This wrapper module provides:

 * YML based gateway configuration
 * A datamodel to work with

## Model

Payable has_many payments. This is an exension that provides a has_many relationship.

**TODO: is there any case where you wouldn't want many payments for a single entity?**

**TODO: automatically set up a has-one relationship on payment, when a dataobject is extended with "Payable".**

Provide functions on Payable: totalPaid, totalOutstanding, totalPending

## Currency handling?

**TODO: Do we allow for making payments in different currencies, or should we always assume/recommend a base currency?**

- need use cases

## Payment state machine

Here are the possible states a payment can have

 * Created - new payment model
 * Authorised - payment has been authorised by gateway
 * Captured - money has been secussfully recieved
 * Completed - completely paid
 * Refund - funds have been returned to payer
 * Voided - (TODO: what does this mean? cancelled? failed?)

 ## Payment logging

 This could either be done by: creating another payment object,
 or by: keeping a log against a payment.

 Things to log:

  * State changes
  * Problems / errors
  * Human notes
  * Gateway-specific data
  * Who performed actions / made changes