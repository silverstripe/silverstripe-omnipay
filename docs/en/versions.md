# SilverStripe Omnipay Versions

This document is targeted at developers who already use the omnipay-module but need to update to another version of omnipay.

New users should use the latest stable release!

For step-by-step instructions, see [Upgrading](Upgrading.md).


## 6.1

 - Adds a complete migration for payment message data from 5.x and older that runs on `db:build`. It also repairs
   sites that already upgraded to 6.0. See [Upgrading](Upgrading.md).
 - Deprecated `PaymentMessage::classForMessageType()`.

## 6.0

 - Requires Silverstripe CMS 6 and PHP 8.3+.
 - Replaced the per-type payment message classes (`PurchaseRequest`, `RefundError`, …) with a single
   `PaymentMessage` class and `Type` field. Message type constants live on the service classes.


## 2.0-rc1

The main API-Changes are:

 - Namespaces added to all Classes that aren't DataObjects (breaking change).
 - `GatewayResponse` has become `SilverStripe\Omnipay\Service\ServiceResponse` (breaking change).
 - Introduces a new API for Payment-Services (old API has been deprecated).
 - Changed config API (old API has been deprecated).

## 2.0-alpha

This is a release that uses the same API as the 1.2 version, but uses Omnipay 2.x instead.

Use this, if you need Omnipay 2.x compatiblity for your application that uses the API of the 1.2 Version of silverstripe-omnipay.

 - Supports Omnipay 2.x
 - Supports purchase with onsite, offsite and Manual-Gateways.

Please note that this version will not be further supported. It's here to ease the transition to the 2.0 version of this module.


## 1.2

Stable release for Omnipay 1.x

 - Supports Omnipay 1.x
 - Supports purchase with onsite, offsite and Manual-Gateways.
