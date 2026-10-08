<?php

namespace SilverStripe\Omnipay\Migration;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Omnipay\Model\Message\PaymentMessage;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

/**
 * Upgrades payment messages written by silverstripe-omnipay 5.x (and earlier) to the single-table
 * {@link PaymentMessage} model introduced in 6.0.
 *
 * Up to 5.x every message kind was its own DataObject subclass (`PurchaseRequest`, `RefundError`, …)
 * and a message's data was split across three tables:
 *
 *  - `Omnipay_PaymentMessage`: Message, ClientIp, PaymentID, UserID and ClassName
 *  - `Omnipay_GatewayMessage`: Gateway, Reference, Code
 *  - `Omnipay_GatewayRequestMessage`: SuccessURL, FailureURL
 *
 * This migrator:
 *
 *  1. Copies the columns from the two legacy sub-tables into `Omnipay_PaymentMessage` (only where the
 *     target column is still empty, so data written since the upgrade is never overwritten).
 *  2. Derives {@link PaymentMessage::$Type} from the legacy ClassName (eg. `PurchaseRequest`) and resets
 *     ClassName to {@link PaymentMessage}.
 *  3. Renames the legacy tables to `_obsolete_*` so you can inspect and drop them when you're ready.
 *
 * All steps use bulk SQL and are idempotent, so it is safe to run on every `dev/build` (which is what
 * {@link PaymentMessage::requireDefaultRecords()} does by default).
 */
class PaymentMessageMigrator
{
    use Configurable;
    use Injectable;

    /**
     * Legacy tables and the columns to copy from each into the {@link PaymentMessage} table.
     */
    private static array $legacy_tables = [
        'Omnipay_GatewayMessage' => ['Gateway', 'Reference', 'Code'],
        'Omnipay_GatewayRequestMessage' => ['SuccessURL', 'FailureURL'],
    ];

    /**
     * Legacy abstract base classes. Records with these class names have no meaningful message type,
     * so only their ClassName is reset.
     */
    private static array $legacy_base_classes = [
        'PaymentMessage',
        'GatewayMessage',
        'GatewayRequestMessage',
        'GatewayResponseMessage',
        'GatewayErrorMessage',
        'GatewayRedirectResponseMessage',
    ];

    /**
     * Whether to rename the legacy tables to `_obsolete_*` once their data has been copied.
     */
    private static bool $rename_legacy_tables = true;

    /**
     * Run the migration.
     *
     * @param bool $dryRun Only count what would change, don't write anything.
     * @return array<string, int> Number of affected rows (or renamed tables), keyed by a human readable step name.
     */
    public function migrate(bool $dryRun = false): array
    {
        $results = [];
        $schema = DB::get_schema();
        $table = DataObject::getSchema()->tableName(PaymentMessage::class);

        if (!$schema->hasTable($table)) {
            return $results;
        }

        foreach ($this->findLegacyTables() as $legacyTable => $columns) {
            foreach ($columns as $column) {
                $count = $this->copyColumn($table, $legacyTable, $column, $dryRun);
                if ($count) {
                    $results["Copied {$column} from {$legacyTable}"] = $count;
                }
            }

            if (!$dryRun && static::config()->get('rename_legacy_tables') && !str_starts_with($legacyTable, '_obsolete_')) {
                $renamedTo = $this->renameToObsolete($legacyTable);
                $results["Renamed {$legacyTable} to {$renamedTo}"] = 1;
            }
        }

        foreach ($this->findLegacyClassNames($table) as $legacyClass) {
            $count = $this->migrateClassName($table, $legacyClass, $dryRun);
            if ($count) {
                $results["Converted {$legacyClass} to Type"] = $count;
            }
        }

        return $results;
    }

    /**
     * Legacy tables that still exist in the database (either under their original name, or already renamed to
     * `_obsolete_*` by an earlier run or a manual cleanup), mapped to the columns that can be copied from them.
     *
     * @return array<string, list<string>>
     */
    protected function findLegacyTables(): array
    {
        $schema = DB::get_schema();
        $tables = [];

        foreach (static::config()->get('legacy_tables') as $legacyTable => $columns) {
            foreach ([$legacyTable, "_obsolete_{$legacyTable}"] as $candidate) {
                if (!$schema->hasTable($candidate)) {
                    continue;
                }
                $existing = array_change_key_case($schema->fieldList($candidate), CASE_LOWER);
                $available = array_values(array_filter(
                    $columns,
                    fn ($column) => isset($existing[strtolower($column)])
                ));
                if ($available) {
                    $tables[$candidate] = $available;
                }
            }
        }

        return $tables;
    }

    /**
     * ClassName values that no longer refer to an existing class. Custom subclasses of {@link PaymentMessage}
     * in your project still exist and are therefore left alone.
     *
     * @return list<string>
     */
    protected function findLegacyClassNames(string $table): array
    {
        $classNames = DB::prepared_query(
            "SELECT DISTINCT \"ClassName\" FROM \"{$table}\" WHERE \"ClassName\" <> ?",
            [PaymentMessage::class]
        )->column();

        return array_values(array_filter(
            $classNames,
            fn ($className) => $className && !class_exists($className)
        ));
    }

    protected function copyColumn(string $table, string $legacyTable, string $column, bool $dryRun): int
    {
        $isEmpty = "(\"{$table}\".\"{$column}\" IS NULL OR \"{$table}\".\"{$column}\" = '')";
        $legacyHasValue = "EXISTS (SELECT 1 FROM \"{$legacyTable}\" WHERE \"{$legacyTable}\".\"ID\" = \"{$table}\".\"ID\""
            . " AND \"{$legacyTable}\".\"{$column}\" IS NOT NULL AND \"{$legacyTable}\".\"{$column}\" <> '')";

        if ($dryRun) {
            return (int) DB::query("SELECT COUNT(*) FROM \"{$table}\" WHERE {$isEmpty} AND {$legacyHasValue}")->value();
        }

        DB::query(
            "UPDATE \"{$table}\" SET \"{$column}\" = ("
            . "SELECT \"{$legacyTable}\".\"{$column}\" FROM \"{$legacyTable}\" WHERE \"{$legacyTable}\".\"ID\" = \"{$table}\".\"ID\""
            . ") WHERE {$isEmpty} AND {$legacyHasValue}"
        );

        return DB::affected_rows();
    }

    protected function migrateClassName(string $table, string $legacyClass, bool $dryRun): int
    {
        if ($dryRun) {
            return (int) DB::prepared_query(
                "SELECT COUNT(*) FROM \"{$table}\" WHERE \"ClassName\" = ?",
                [$legacyClass]
            )->value();
        }

        $type = $this->typeForLegacyClass($legacyClass);
        if ($type !== null) {
            DB::prepared_query(
                "UPDATE \"{$table}\" SET \"Type\" = ? WHERE \"ClassName\" = ? AND (\"Type\" IS NULL OR \"Type\" = '')",
                [$type, $legacyClass]
            );
        }

        DB::prepared_query(
            "UPDATE \"{$table}\" SET \"ClassName\" = ? WHERE \"ClassName\" = ?",
            [PaymentMessage::class, $legacyClass]
        );

        return DB::affected_rows();
    }

    /**
     * Map a legacy class name such as `SilverStripe\Omnipay\Model\Message\PurchaseRequest` (5.x) or
     * `PurchaseRequest` (3.x) to its message type, eg. `PurchaseRequest`.
     */
    public function typeForLegacyClass(string $legacyClass): ?string
    {
        $pos = strrpos($legacyClass, '\\');
        $shortName = $pos === false ? $legacyClass : substr($legacyClass, $pos + 1);

        if ($shortName === '' || in_array($shortName, static::config()->get('legacy_base_classes'), true)) {
            return null;
        }

        return $shortName;
    }

    /**
     * @return string The new table name
     */
    protected function renameToObsolete(string $legacyTable): string
    {
        $schema = DB::get_schema();
        $renameTo = "_obsolete_{$legacyTable}";
        $suffix = 2;
        while ($schema->hasTable($renameTo)) {
            $renameTo = "_obsolete_{$legacyTable}{$suffix}";
            $suffix++;
        }
        $schema->renameTable($legacyTable, $renameTo);

        return $renameTo;
    }
}
