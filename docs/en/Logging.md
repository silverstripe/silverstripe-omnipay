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

There's a config setting `SilverStripe\Omnipay\Helper.logStyle` that defines how data is being logged. It can take 3 different values:

- `'full'`: Verbose logging, log all information. **Attention**: This will automatically turn into `'verbose'` on a live environment!
- `'verbose'`: Verbose logging, but strips out sensitive information
- `'simple'`: Simplified messages (only title, message and code)

There's also a setting that controls which data-fields will be sanitized, so that they don't show up in the logs. If you're logging on 
a live environment, make sure to NOT log any sensitive information, such as credit-card numbers and CVV numbers!

You can control this "blacklist" via the `SilverStripe\Omnipay\Helper.loggingBlacklist` setting. By default the Helper class is configured like this:

```yml
SilverStripe\Omnipay\Helper:
  logStyle: 'verbose'
  loggingBlacklist:
    - 'card'
    - 'token'
    - 'cvv'
```
