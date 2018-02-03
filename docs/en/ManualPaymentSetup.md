# Manual Payment Setup for silverstripe-omnipay

When using silverstripe-omnipay, any required Omnipay modules must be installed via composer.

```
composer require omnipay/manual
```

Then in your config file (typically `mysite/_config/config.yml`, or `mysite/_config/payment.yml`)

```yaml
---
Name: payment
---
Payment:
  allowed_gateways:
    - 'Manual'
```
