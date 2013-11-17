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
 * Token billing - token that represents a credit card, for delayed, or reocurring payments.

### Not sure if in scope
 * Provide forms?

### Out of scope features

 * Subscripting / reoccurring billing
 * Credit notes
 * Payment via gift vouchers