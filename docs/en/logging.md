# Logging

This module logs as much information to the database as possible. This includes:

  * State changes
  * Problems / errors
  * Human notes
  * Gateway-specific data
  * Who performed actions / made changes


Payment messages can be Request, Response, or Error.

Errors could later be split into:
 * Communications
 * Validation
 * Failures

PurchaseRequest
 * PurchasedResponse
 * PurchaseRedirectResponse
 * PurchaseError

CompletePurchaseRequest
 * PurchasedResponse
 * CompletePurchaseError

AuthorizeRequest
 * AuthorizedResponse
 * AuthorizeRedirectResponse
 * AuthorizeError

CompleteAuthorizeRequest
 * AuthorizedResponse
 * CompleteAuthorizeError

CaptureRequest
 * CapturedResponse
 * CaptureError

RefundRequest
 * RefundedResponse
 * RefundError

VoidRequest
 * VoidedResponse
 * VoidError