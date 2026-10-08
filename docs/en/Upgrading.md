# Upgrading

## Upgrading from 5.x to 6.x

6.0 replaced the class-per-type payment messages with a single model ([#123](https://github.com/silverstripe/silverstripe-omnipay/issues/123),
[#183](https://github.com/silverstripe/silverstripe-omnipay/issues/183)). **Use 6.1 or later**: 6.1 ships the migration
that brings existing message data across correctly.

**If you are upgrading from 5.x or older, or you already upgraded to 6.0 from 5.x, read the
[payment message migration](#payment-message-migration) section below.** Sites that started on 6.x have no legacy
data to migrate.

### TL;DR

1. Update the constraint: `composer require silverstripe/silverstripe-omnipay:^6.1`
2. Back up your database.
3. Optional: preview the migration with `vendor/bin/sake tasks:MigratePaymentMessageTypesTask --dry-run`
4. Run `vendor/bin/sake db:build --flush`. The message migration runs as part of the build.
5. Replace any code that references the old message classes (see [Updating your code](#updating-your-code)).
6. Once you're happy, drop the `_obsolete_Omnipay_GatewayMessage` and `_obsolete_Omnipay_GatewayRequestMessage` tables.

## Payment message migration

### What changed

Every `Payment` has many `Messages`, a log of each request, response, error and notification exchanged with the
gateway.

**Up to 5.x**, each kind of message was its own `DataObject` subclass. There were 41 classes in total
(`PurchaseRequest`, `RefundError`, `NotificationSuccessful`, …) and a single message was spread across three tables:

| Table                            | Columns                                         |
|----------------------------------|-------------------------------------------------|
| `Omnipay_PaymentMessage`         | `ClassName`, `Message`, `ClientIp`, `PaymentID`, `UserID` |
| `Omnipay_GatewayMessage`         | `Gateway`, `Reference`, `Code`                  |
| `Omnipay_GatewayRequestMessage`  | `SuccessURL`, `FailureURL`                      |

**Since 6.0**, there is a single `SilverStripe\Omnipay\Model\Message\PaymentMessage` class backed by a single
`Omnipay_PaymentMessage` table. What used to be the class name is now stored in the indexed `Type` column. For
example, a `SilverStripe\Omnipay\Model\Message\PurchaseRequest` record becomes a `PaymentMessage` with
`Type = 'PurchaseRequest'`.

### Why 6.1 includes a migration (again)

The upgrade path shipped with 6.0 was incomplete:

- The `MigratePaymentMessageTypesTask` only populated `Type`. It never copied `Gateway`, `Reference`, `Code`,
  `SuccessURL` or `FailureURL` out of the two legacy tables, so those values appeared empty on upgraded messages.
- `_config/legacy.yml` configured class name remapping on `DatabaseAdmin`, a class that no longer exists in
  Silverstripe CMS 6, so it had no effect.

Nothing was deleted: the legacy tables were left untouched in your database, so 6.1 can recover everything.

### What the migration does

`SilverStripe\Omnipay\Migration\PaymentMessageMigrator` runs on every `db:build` (via
`PaymentMessage::requireDefaultRecords()`) and:

1. Copies `Gateway`, `Reference` and `Code` from `Omnipay_GatewayMessage`, and `SuccessURL` and `FailureURL` from
   `Omnipay_GatewayRequestMessage`, into `Omnipay_PaymentMessage`. It **only fills columns that are empty**, so
   anything written since you upgraded is never overwritten.
2. Sets `Type` from each legacy `ClassName` (both namespaced 5.x names and un-namespaced 3.x names), then resets
   `ClassName` to `PaymentMessage`. The legacy abstract base classes (`GatewayMessage`, `GatewayRequestMessage`, …)
   have no meaningful type, so for those only `ClassName` is reset. `ClassName` values for classes that still exist,
   such as your own `PaymentMessage` subclasses, are left alone.
3. Renames the legacy tables to `_obsolete_Omnipay_GatewayMessage` and `_obsolete_Omnipay_GatewayRequestMessage`.
   They are **not** dropped.

Every step is a bulk SQL update, so it stays fast on large message tables. Every step is also idempotent: once there
is nothing left to migrate, a build makes no changes. The `db:build` output lists each step it performed, for example:

```
Payment messages: Copied Gateway from Omnipay_GatewayMessage (18342)
Payment messages: Converted SilverStripe\Omnipay\Model\Message\PurchaseRequest to Type (4120)
```

This works the same whether you come straight from 5.x or already upgraded to 6.0. On a 6.0 site, `Type` is already
set and the migrator backfills the missing gateway and URL columns.

### Previewing or running it manually

The same migration is available as a task. With `--dry-run` it only reports what it would change:

```bash
vendor/bin/sake tasks:MigratePaymentMessageTypesTask --dry-run
vendor/bin/sake tasks:MigratePaymentMessageTypesTask
```

If you'd rather control when the migration runs (eg. as a separate deployment step), disable it on build:

```yaml
SilverStripe\Omnipay\Model\Message\PaymentMessage:
  migrate_legacy_messages_on_build: false
```

To keep the legacy tables under their original names after migrating:

```yaml
SilverStripe\Omnipay\Migration\PaymentMessageMigrator:
  rename_legacy_tables: false
```

### Verifying and cleaning up

After the build, these queries should return `0`:

```sql
-- Messages still using a legacy class name
SELECT COUNT(*) FROM "Omnipay_PaymentMessage"
WHERE "ClassName" <> 'SilverStripe\\Omnipay\\Model\\Message\\PaymentMessage';

-- Legacy rows whose gateway didn't make it across
SELECT COUNT(*) FROM "Omnipay_PaymentMessage" m
JOIN "_obsolete_Omnipay_GatewayMessage" g ON g."ID" = m."ID"
WHERE (m."Gateway" IS NULL OR m."Gateway" = '') AND g."Gateway" <> '';
```

Once you're satisfied, drop the obsolete tables:

```sql
DROP TABLE "_obsolete_Omnipay_GatewayMessage";
DROP TABLE "_obsolete_Omnipay_GatewayRequestMessage";
```

### Updating your code

Search your project for `Model\Message\` and for the old class names. Common replacements:

| Before (5.x)                                                    | After (6.0+)                                                                                   |
|-----------------------------------------------------------------|------------------------------------------------------------------------------------------------|
| `PurchaseRequest::get()`                                        | `PaymentMessage::get()->filter('Type', PurchaseService::MESSAGE_PURCHASE_REQUEST)`             |
| `$payment->Messages()->filter('ClassName', PurchasedResponse::class)` | `$payment->Messages()->filter('Type', PurchaseService::MESSAGE_PURCHASED_RESPONSE)`        |
| Latest message of a kind                                        | `$payment->getLatestMessageOfType(PurchaseService::MESSAGE_PURCHASED_RESPONSE)`                |
| `$message instanceof GatewayErrorMessage`                       | `in_array($message->Type, PurchaseService::ERROR_MESSAGE_TYPES)` (each service has `ERROR_MESSAGE_TYPES`) |
| `$message instanceof GatewayRequestMessage`                     | `PaymentMessage::isRequestMessageType($message->Type)`                                         |
| `$message->ClassName` / `$message->i18n_singular_name()`        | `$message->Type` / `$message->getTitle()` (translated via `PaymentMessage.TYPE_<Type>`)       |
| `PaymentMessage::classForMessageType($type)` (6.0)              | Deprecated in 6.1: always `PaymentMessage::class`. Use `PaymentMessage::create()`             |

The type constants live on the service that creates the message. See [Payment messages](index.md#payment-messages) for
the full list.

Code that extends or replaces a specific message subclass through Injector or extensions has no direct equivalent.
Apply the extension to `PaymentMessage` and check `$this->owner->Type` instead.

### Translations

Message titles are translated with `SilverStripe\Omnipay\Model\Message\PaymentMessage.TYPE_<Type>` keys, eg.
`TYPE_PurchaseRequest`. Add a key for any custom message types your project creates.

## Changes in 6.1

- `PaymentMessage::classForMessageType()` is deprecated. It was a 6.0 transition helper that always returns
  `PaymentMessage::class`.
- `MigratePaymentMessageTypesTask` now delegates to `PaymentMessageMigrator`, migrates all legacy columns, and
  supports `--dry-run`.
- `_config/legacy.yml` now configures `SilverStripe\Dev\Command\DbBuild.classname_value_remapping`, and no longer
  remaps legacy message classes (the migrator handles those).
- Build tasks register with the correct Silverstripe CMS 6 command names: `tasks:MigratePaymentMessageTypesTask`
  and `tasks:CleanupAbandonedPaymentsTask`.
