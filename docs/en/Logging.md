# Logging

This module uses standard SilverStripe logging (via [monolog](https://github.com/Seldaek/monolog)).
Please read the [SilverStripe logging documentation](https://docs.silverstripe.org/en/4/developer_guides/debugging/error_handling/) on how to set up basic logging.

By default, the Omnipay module logs to the default logger of SilverStripe.
If you'd like, you can create a custom Log for Omnipay though (even with a custom error-level).

Here's an example config that configures a separate log output for SilverStripe, as well as one for Omnipay:

```yml
---
Name: debug
After: omnipay-logging
---
SilverStripe\Core\Injector\Injector:
  Psr\Log\LoggerInterface:
    calls:
      LogFileHandler: [ pushHandler, [ %$LogFileHandler ] ]
  LogFileHandler:
    class: Monolog\Handler\StreamHandler
    constructor:
      - "silverstripe.log"
      - "warning"
  # Here we configure the separate log for omnipay
  SilverStripe\Omnipay\Logger:
    type: singleton
    class: Monolog\Logger
    constructor:
      - 'ss-omnipay-log'
    calls:
      pushLogFileHandler: [ pushHandler, [ %$OmnipayLogFileHandler ] ]
  # The separate handler logs to a file "omnipay.log", starting at the "info" level
  OmnipayLogFileHandler:
    class: Monolog\Handler\StreamHandler
    constructor:
      - "omnipay.log"
      - "info"

```

## Configuring logger output

Next to using different handlers to log to different formats, you can also control how much of the Omnipay-Message Data will be logged.

There's a config setting `SilverStripe\Omnipay\Helper\Logging.logStyle` that defines how data is being logged. It can take 3 different values:

- `'full'`: Verbose logging, log all information. **Attention**: This will automatically turn into `'verbose'` on a live environment!
- `'verbose'`: Verbose logging, but strips out sensitive information
- `'simple'`: Simplified messages (only title, message and code)

There's also a setting that controls which data-fields will be sanitized, so that they don't show up in the logs. If you're logging on
a live environment, make sure to NOT log any sensitive information, such as credit-card numbers and CVV numbers!

You can control this "blacklist" via the `SilverStripe\Omnipay\Helper\Logging.loggingBlacklist` setting. By default the module is configured with a blacklist covering the most common sensitive payment parameters:

```yml
SilverStripe\Omnipay\Helper\Logging:
  logStyle: 'verbose'
  loggingBlacklist:
    - card
    - token
    - cvv
    - cvc
    - number
    - password
```

You can extend the blacklist in your project config by adding additional field names that are specific to your payment gateway implementation.

## Changing the log level of payment messages

Every payment message is logged: error message types (eg. `PurchaseError`, `CompletePurchaseError`) as `error`,
everything else as `info`. Not every error message type is an application error though. When a customer abandons
an offsite payment, the gateway can still notify the site that this attempt expired or was cancelled. That
notification is stored as a `CompletePurchaseError` and logged as `error`, so a site that mails its errors gets an
error mail for an order that the customer then simply paid with a new attempt.

Use the `updateLogLevel` extension hook to pick a different [PSR-3 level](https://www.php-fig.org/psr/psr-3/) for
such messages, or set the level to `null` to not log the message at all. The payment message itself is always
stored. The hook gets the level (by reference), the message type and the message data: the same array that is
stored and logged, including the raw gateway response under `Data`. An unknown level falls back to the default.

Example for Mollie, where the gateway response has a `status`:

```php
use Psr\Log\LogLevel;
use SilverStripe\Core\Extension;
use SilverStripe\Omnipay\Service\PurchaseService;

class PaymentLogLevelExtension extends Extension
{
    public function updateLogLevel(?string &$level, string $type, mixed $data): void
    {
        $status = $data['Data']['status'] ?? null;
        if ($type === PurchaseService::MESSAGE_COMPLETE_PURCHASE_ERROR
            && in_array($status, ['open', 'pending', 'canceled', 'expired', 'failed'], true)
        ) {
            // the customer didn't finish this payment attempt (or the bank declined it): not an application error
            $level = LogLevel::NOTICE;
        }
    }
}
```

```yml
SilverStripe\Omnipay\Service\PurchaseService:
  extensions:
    - PaymentLogLevelExtension
```

Errors from the gateway API itself (eg. an invalid API key) have no such status, so they are still logged as
`error`. Exceptions are logged through the exception logger and are not affected by this hook.

## Disable non-fatal error logging

In production environments, it is often desirable to disable logging anything that isn't an application error. This can be achieved
by disabling the default `SilverStripe\Omnipay\Logger` logger instance, and adding a new logger for logging exceptions:

```yml
---
Name: payment-logging
After:
  - '#omnipay-logging'
---
SilverStripe\Core\Injector\Injector:
  SilverStripe\Omnipay\Logger:
    class: 'Psr\Log\NullLogger'
  SilverStripe\Omnipay\ExceptionLogger: '%$Psr\Log\LoggerInterface'
```
