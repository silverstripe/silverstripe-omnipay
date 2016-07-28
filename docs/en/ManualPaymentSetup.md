# Manual Payment Setup for silverstripe-omnipay

When using silverstripe-omnipay, any required Omnipay modules must be installed via composer.

```
composer require omnipay/manual dev-master
```

Then in your mysite/_config/_config.yml file
```
---
Name: payment
---
Payment:
  file_logging: 1
  allowed_gateways:
    - 'Manual'
```
