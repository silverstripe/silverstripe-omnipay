<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Omnipay\Model\Message\PaymentMessage;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tasks\CleanupAbandonedPaymentsTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;

class CleanupAbandonedPaymentsTaskTest extends SapphireTest
{
    protected $usesDatabase = true;

    private array $payments = [];

    protected function setUp(): void
    {
        parent::setUp();

        DBDatetime::set_mock_now('2026-01-01 12:00:00');
        $this->payments['oldPending'] = $this->createPayment('PendingPurchase');
        $this->payments['oldCreated'] = $this->createPayment('Created');
        $this->payments['oldCaptured'] = $this->createPayment('Captured');
        $this->payments['oldPendingCapture'] = $this->createPayment('PendingCapture');
        $this->payments['oldWithReference'] = $this->createPayment('PendingPurchase', 'ref-1');

        DBDatetime::set_mock_now('2026-01-08 12:00:00');
        $this->payments['recentPending'] = $this->createPayment('PendingAuthorization');

        DBDatetime::set_mock_now('2026-01-10 12:00:00');
    }

    protected function tearDown(): void
    {
        DBDatetime::clear_mock_now();
        parent::tearDown();
    }

    public function testDryRunDoesNotDelete(): void
    {
        [$code, $output] = $this->runTask([]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Found 2 abandoned payment(s)', $output);
        $this->assertCount(6, Payment::get());
    }

    public function testDeleteRemovesAbandonedPaymentsAndMessages(): void
    {
        [$code, $output] = $this->runTask(['--delete' => true]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Deleted 2 abandoned payment(s)', $output);

        $this->assertNull(Payment::get()->byID($this->payments['oldPending']));
        $this->assertNull(Payment::get()->byID($this->payments['oldCreated']));
        $this->assertCount(0, PaymentMessage::get()->filter('PaymentID', $this->payments['oldPending']));

        foreach (['oldCaptured', 'oldPendingCapture', 'oldWithReference', 'recentPending'] as $key) {
            $this->assertNotNull(Payment::get()->byID($this->payments[$key]), "$key should be kept");
        }
    }

    public function testDaysOption(): void
    {
        [, $output] = $this->runTask(['--delete' => true, '--days' => '1']);
        $this->assertStringContainsString('Deleted 3 abandoned payment(s)', $output);
        $this->assertNull(Payment::get()->byID($this->payments['recentPending']));

        [$code] = $this->runTask(['--days' => 'abc']);
        $this->assertSame(Command::INVALID, $code);
    }

    private function createPayment(string $status, ?string $reference = null): int
    {
        $payment = Payment::create()->init('Dummy', 10, 'NZD');
        $payment->Status = $status;
        $payment->TransactionReference = $reference;
        $payment->write();

        PaymentMessage::create(['PaymentID' => $payment->ID, 'Type' => 'PurchaseRequest'])->write();

        return $payment->ID;
    }

    private function runTask(array $args): array
    {
        $task = CleanupAbandonedPaymentsTask::create();
        $input = new ArrayInput($args, new InputDefinition($task->getOptions()));
        $buffer = new BufferedOutput();
        $code = $task->run($input, new PolyOutput(PolyOutput::FORMAT_ANSI, wrappedOutput: $buffer));

        return [$code, $buffer->fetch()];
    }
}
