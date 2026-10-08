<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Migration\PaymentMessageMigrator;
use SilverStripe\Omnipay\Model\Message\PaymentMessage;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBEnum;

/**
 * Simulates a database upgraded from omnipay 5.x, where message data was split across three tables and the message
 * type was stored as the ClassName.
 */
class PaymentMessageMigratorTest extends SapphireTest
{
    protected $usesDatabase = true;

    private const LEGACY_NAMESPACE = 'SilverStripe\\Omnipay\\Model\\Message\\';

    protected function setUp(): void
    {
        parent::setUp();

        if (!DB::get_conn() instanceof MySQLDatabase) {
            $this->markTestSkipped('Legacy schema simulation requires MySQL');
        }

        // After a real upgrade, the ClassName enum still contains the obsolete values (see DBEnum::getEnumObsolete).
        // Loosen the column so the test can insert them.
        DB::query('ALTER TABLE "Omnipay_PaymentMessage" MODIFY "ClassName" VARCHAR(255)');

        DB::query('CREATE TABLE "Omnipay_GatewayMessage" ('
            . '"ID" INT NOT NULL PRIMARY KEY, "Gateway" VARCHAR(50), "Reference" VARCHAR(255), "Code" VARCHAR(50))');
        DB::query('CREATE TABLE "Omnipay_GatewayRequestMessage" ('
            . '"ID" INT NOT NULL PRIMARY KEY, "SuccessURL" MEDIUMTEXT, "FailureURL" MEDIUMTEXT)');
    }

    protected function tearDown(): void
    {
        if (DB::get_conn() instanceof MySQLDatabase) {
            DB::query('DELETE FROM "Omnipay_PaymentMessage"');
            foreach (['Omnipay_GatewayMessage', 'Omnipay_GatewayRequestMessage'] as $table) {
                DB::query("DROP TABLE IF EXISTS \"{$table}\"");
                DB::query("DROP TABLE IF EXISTS \"_obsolete_{$table}\"");
            }
            // Restore the ClassName enum
            DBEnum::reset();
            DB::get_schema()->schemaUpdate(function () {
                PaymentMessage::singleton()->requireTable();
            });
        }

        parent::tearDown();
    }

    public function testMigratesLegacyMessages(): void
    {
        $this->insertLegacyMessage(1, self::LEGACY_NAMESPACE . 'PurchaseRequest', [
            'Gateway' => 'PayPal_Express', 'Reference' => 'REF-1', 'Code' => '',
        ], [
            'SuccessURL' => 'https://example.com/success', 'FailureURL' => 'https://example.com/failure',
        ]);
        $this->insertLegacyMessage(2, self::LEGACY_NAMESPACE . 'RefundError', [
            'Gateway' => 'PayPal_Express', 'Reference' => 'REF-2', 'Code' => '500',
        ]);
        // Pre-namespace (3.x) class name
        $this->insertLegacyMessage(3, 'PurchasedResponse', ['Gateway' => 'Manual']);
        // Abstract base class: has no message type
        $this->insertLegacyMessage(4, self::LEGACY_NAMESPACE . 'GatewayMessage', ['Gateway' => 'Dummy']);

        $results = PaymentMessageMigrator::create()->migrate();

        $this->assertSame(4, $results['Copied Gateway from Omnipay_GatewayMessage'] ?? null);
        $this->assertSame(1, $results['Renamed Omnipay_GatewayMessage to _obsolete_Omnipay_GatewayMessage'] ?? null);

        $purchase = PaymentMessage::get()->byID(1);
        $this->assertSame(PaymentMessage::class, $purchase->ClassName);
        $this->assertSame('PurchaseRequest', $purchase->Type);
        $this->assertSame('PayPal_Express', $purchase->Gateway);
        $this->assertSame('REF-1', $purchase->Reference);
        $this->assertSame('https://example.com/success', $purchase->SuccessURL);
        $this->assertSame('https://example.com/failure', $purchase->FailureURL);

        $error = PaymentMessage::get()->byID(2);
        $this->assertSame('RefundError', $error->Type);
        $this->assertSame('500', $error->Code);
        $this->assertEmpty($error->SuccessURL);

        $this->assertSame('PurchasedResponse', PaymentMessage::get()->byID(3)->Type);

        $base = PaymentMessage::get()->byID(4);
        $this->assertSame(PaymentMessage::class, $base->ClassName);
        $this->assertEmpty($base->Type);
        $this->assertSame('Dummy', $base->Gateway);

        $schema = DB::get_schema();
        $this->assertFalse($schema->hasTable('Omnipay_GatewayMessage'));
        $this->assertTrue($schema->hasTable('_obsolete_Omnipay_GatewayMessage'));
        $this->assertFalse($schema->hasTable('Omnipay_GatewayRequestMessage'));
        $this->assertTrue($schema->hasTable('_obsolete_Omnipay_GatewayRequestMessage'));
    }

    public function testSecondRunIsNoOp(): void
    {
        $this->insertLegacyMessage(1, self::LEGACY_NAMESPACE . 'VoidRequest', ['Gateway' => 'Dummy']);

        $this->assertNotEmpty(PaymentMessageMigrator::create()->migrate());
        $this->assertSame([], PaymentMessageMigrator::create()->migrate());
        $this->assertSame('VoidRequest', PaymentMessage::get()->byID(1)->Type);
    }

    /**
     * Sites that already upgraded to 6.0 ran the old task, which set Type but left the legacy tables behind.
     */
    public function testBackfillsMessagesUpgradedBy60WithoutOverwriting(): void
    {
        $this->insertLegacyMessage(1, PaymentMessage::class, ['Gateway' => 'PayPal_Express', 'Reference' => 'OLD']);
        DB::query('UPDATE "Omnipay_PaymentMessage" SET "Type" = \'CaptureRequest\', "Reference" = \'NEW\' WHERE "ID" = 1');

        PaymentMessageMigrator::create()->migrate();

        $message = PaymentMessage::get()->byID(1);
        $this->assertSame('CaptureRequest', $message->Type);
        $this->assertSame('PayPal_Express', $message->Gateway, 'Empty columns are filled from the legacy table');
        $this->assertSame('NEW', $message->Reference, 'Existing values are never overwritten');
    }

    public function testExistingClassNamesAreLeftAlone(): void
    {
        // Any class that still exists (eg. a project's own PaymentMessage subclass) must not be touched
        $this->insertLegacyMessage(1, self::class, []);

        PaymentMessageMigrator::create()->migrate();

        $className = DB::query('SELECT "ClassName" FROM "Omnipay_PaymentMessage" WHERE "ID" = 1')->value();
        $this->assertSame(self::class, $className);
    }

    public function testDryRunDoesNotChangeAnything(): void
    {
        $this->insertLegacyMessage(1, self::LEGACY_NAMESPACE . 'AuthorizeRequest', ['Gateway' => 'Dummy'], [
            'SuccessURL' => 'https://example.com/success',
        ]);

        $results = PaymentMessageMigrator::create()->migrate(true);

        $this->assertSame([
            'Copied Gateway from Omnipay_GatewayMessage' => 1,
            'Copied SuccessURL from Omnipay_GatewayRequestMessage' => 1,
            'Converted ' . self::LEGACY_NAMESPACE . 'AuthorizeRequest to Type' => 1,
        ], $results);

        $row = DB::query('SELECT * FROM "Omnipay_PaymentMessage" WHERE "ID" = 1')->record();
        $this->assertSame(self::LEGACY_NAMESPACE . 'AuthorizeRequest', $row['ClassName']);
        $this->assertEmpty($row['Type']);
        $this->assertEmpty($row['Gateway']);
        $this->assertTrue(DB::get_schema()->hasTable('Omnipay_GatewayMessage'));
    }

    public function testRunsOnBuild(): void
    {
        $this->insertLegacyMessage(1, self::LEGACY_NAMESPACE . 'CapturedResponse', ['Gateway' => 'Dummy']);

        PaymentMessage::config()->set('migrate_legacy_messages_on_build', false);
        PaymentMessage::singleton()->requireDefaultRecords();
        $this->assertEmpty(DB::query('SELECT "Type" FROM "Omnipay_PaymentMessage" WHERE "ID" = 1')->value());

        PaymentMessage::config()->set('migrate_legacy_messages_on_build', true);
        PaymentMessage::singleton()->requireDefaultRecords();
        $this->assertSame('CapturedResponse', PaymentMessage::get()->byID(1)->Type);
    }

    public function testTypeForLegacyClass(): void
    {
        $migrator = PaymentMessageMigrator::create();
        $this->assertSame('PurchaseRequest', $migrator->typeForLegacyClass(self::LEGACY_NAMESPACE . 'PurchaseRequest'));
        $this->assertSame('NotificationError', $migrator->typeForLegacyClass('NotificationError'));
        $this->assertNull($migrator->typeForLegacyClass(self::LEGACY_NAMESPACE . 'GatewayRequestMessage'));
        $this->assertNull($migrator->typeForLegacyClass('PaymentMessage'));
    }

    /**
     * @param array<string, string> $gatewayData Row for Omnipay_GatewayMessage (skipped when empty)
     * @param array<string, string> $requestData Row for Omnipay_GatewayRequestMessage (skipped when empty)
     */
    private function insertLegacyMessage(int $id, string $className, array $gatewayData, array $requestData = []): void
    {
        DB::prepared_query(
            'INSERT INTO "Omnipay_PaymentMessage" ("ID", "ClassName", "Message", "Created", "LastEdited")'
            . ' VALUES (?, ?, ?, NOW(), NOW())',
            [$id, $className, "Legacy message {$id}"]
        );

        foreach (['Omnipay_GatewayMessage' => $gatewayData, 'Omnipay_GatewayRequestMessage' => $requestData] as $table => $data) {
            if (!$data) {
                continue;
            }
            $columns = '"ID", "' . implode('", "', array_keys($data)) . '"';
            $placeholders = implode(', ', array_fill(0, count($data) + 1, '?'));
            DB::prepared_query(
                "INSERT INTO \"{$table}\" ({$columns}) VALUES ({$placeholders})",
                array_merge([$id], array_values($data))
            );
        }
    }
}
