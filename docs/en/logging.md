# Logging

This module logs as much information to the database as possible. This includes:

  * State changes
  * Problems / errors
  * Human notes
  * Gateway-specific data
  * Who performed actions / made changes

Here is the class structure:

 * PaymentMessage
  * GatewayTransaction
  * GatewayRequestTransaction
  * GatewayErrorTransaction
  * GatewayResponseTransaction
  * PaymentComment
