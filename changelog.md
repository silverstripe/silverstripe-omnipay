# SilverStripe Omnipay Versions

All notable changes to this project will be documented in this file. If you're unsure which version of this project you should use, read the [versions-readme](docs/md/versions.md).

This project adheres to [Semantic Versioning](http://semver.org/).

## 2.0

 - Omnipay 2.x compatible (requires Omnipay 2.4 or higher)
 - Deprecated old config API in favor of a more flexible config structure ([#105](https://github.com/silverstripe/silverstripe-omnipay/pull/105))
 - Deprecated snake_case static methods (transition to PSR-2) ([#104](https://github.com/silverstripe/silverstripe-omnipay/pull/104))
 - Introduced namespaces for all classes that aren't DataObjects.
 - Introduced `ServiceFactory` class that should be used to instantiate payment-services.
 - Implemented services for all common omnipay methods: *Purchase*, *authorize*, *capture*, *refund*, *void*.
 - Added GridField Buttons that enable Users to capture/refund/void Payments directly from the CMS.
 - Implemented support for asynchronous status notifications from payment providers.
 - Added additional statuses to `Payment` to mark a status as pending (`PendingAuthorization`, `PendingPurchase`, `PendingCapture`, `PendingRefund`, `PendingVoid`)
 - `GatewayResponse` becomes `ServiceResponse`
 - Unit-Test coverage greatly increased.
 - Changelog added
