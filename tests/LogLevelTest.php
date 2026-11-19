<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use Psr\Log\LogLevel;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\Omnipay\Tests\Extensions\TestLogLevelExtension;
use SilverStripe\Omnipay\Tests\Helper\TestLogger;

/**
 * Log levels of payment messages and the `updateLogLevel` extension hook
 */
class LogLevelTest extends FunctionalTest
{
    use PaymentTestTrait {
        setUp as protected paymentTestSetUp;
    }

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected static $required_extensions = [
        PurchaseService::class => [
            TestLogLevelExtension::class,
        ],
    ];

    protected TestLogger $logger;

    protected function setUp(): void
    {
        $this->paymentTestSetUp();
        TestLogLevelExtension::reset();
        $this->logger = new TestLogger();
    }

    protected function tearDown(): void
    {
        TestLogLevelExtension::reset();
        parent::tearDown();
    }

    public function testErrorMessagesAreLoggedAsErrorByDefault(): void
    {
        $this->failedPurchase();

        $this->assertSame([LogLevel::INFO], $this->logger->levelsFor('PurchaseRequest (Dummy)'));
        $this->assertSame([LogLevel::ERROR], $this->logger->levelsFor('PurchaseError (Dummy)'));
    }

    public function testExtensionReceivesTheDefaultLevelAndMessageData(): void
    {
        $this->failedPurchase();

        $calls = array_values(array_filter(
            TestLogLevelExtension::$calls,
            fn (array $call) => $call['type'] === PurchaseService::MESSAGE_PURCHASE_ERROR
        ));
        $this->assertCount(1, $calls);
        $this->assertSame(LogLevel::ERROR, $calls[0]['level']);
        $this->assertIsArray($calls[0]['data']);
        $this->assertSame('Dummy', $calls[0]['data']['Gateway']);
        $this->assertArrayHasKey('Data', $calls[0]['data'], 'the raw gateway response data is passed along');
    }

    public function testExtensionCanChangeTheLogLevel(): void
    {
        TestLogLevelExtension::$type = PurchaseService::MESSAGE_PURCHASE_ERROR;
        TestLogLevelExtension::$level = LogLevel::NOTICE;

        $payment = $this->failedPurchase();

        $this->assertSame([LogLevel::NOTICE], $this->logger->levelsFor('PurchaseError (Dummy)'));
        $this->assertSame([LogLevel::INFO], $this->logger->levelsFor('PurchaseRequest (Dummy)'));
        // the payment message is stored as before
        SapphireTest::assertListContains([['Type' => PurchaseService::MESSAGE_PURCHASE_ERROR]], $payment->Messages());
    }

    public function testExtensionCanSkipLogging(): void
    {
        TestLogLevelExtension::$type = PurchaseService::MESSAGE_PURCHASE_ERROR;
        TestLogLevelExtension::$level = null;

        $payment = $this->failedPurchase();

        $this->assertSame([], $this->logger->levelsFor('PurchaseError (Dummy)'));
        SapphireTest::assertListContains([['Type' => PurchaseService::MESSAGE_PURCHASE_ERROR]], $payment->Messages());
    }

    public function testUnknownLevelFallsBackToTheDefault(): void
    {
        TestLogLevelExtension::$type = PurchaseService::MESSAGE_PURCHASE_ERROR;
        TestLogLevelExtension::$level = 'not-a-level';

        $payment = $this->failedPurchase();

        $this->assertSame([LogLevel::ERROR], $this->logger->levelsFor('PurchaseError (Dummy)'));
        SapphireTest::assertListContains([['Type' => PurchaseService::MESSAGE_PURCHASE_ERROR]], $payment->Messages());
    }

    /**
     * A declined onsite payment with the Dummy gateway: logs a PurchaseRequest and a PurchaseError
     */
    protected function failedPurchase(): \SilverStripe\Omnipay\Model\Payment
    {
        $payment = $this->payment;
        $service = PurchaseService::create($payment);
        $service->setLogger($this->logger);
        $response = $service->initiate([
            'firstName' => 'joe',
            'lastName' => 'bloggs',
            'number' => '4111111111111111', // this card will decline
            'expiryMonth' => '5',
            'expiryYear' => date('Y', strtotime('+1 year')),
        ]);
        $this->assertTrue($response->isError());
        return $payment;
    }
}
