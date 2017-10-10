<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\Tests\Extensions\TestPaymentExtension;
use SilverStripe\Omnipay\Tests\Model\TestOrder;

class PayableTest extends SapphireTest
{
    protected $order;

    protected static $fixture_file = 'PayableTest.yml';

    protected static $extra_dataobjects = [
        TestOrder::class
    ];

    protected static $required_extensions = [
        Payment::class => [
            TestPaymentExtension::class
        ]
    ];

    public function setUp()
    {
        parent::setUp();

        Config::modify()->set(Payment::class, 'file_logging', 0);

        $this->order = $this->objFromFixture(TestOrder::class, 'order1');
    }

    /**
     * Test if the relation from Order to the Payments is correctly established
     */
    public function testRelation()
    {
        $payments = $this->order->Payments();

        $this->assertNotNull($payments);
        $this->assertEquals(4, $payments->Count());
    }

    /**
     * Test the total paid amount
     */
    public function testPaidAmounts()
    {
        // the captured payments amount USD 30
        $this->assertEquals(30, $this->order->TotalPaid());

        // the captured & authorized payments amount USD 37
        // The Authorized manual payment should not count!
        $this->assertEquals(37, $this->order->TotalPaidOrAuthorized());

        // capture the pending payment
        $pending = $this->order->Payments()->filter('Status', 'PendingCapture')->first();
        $pending->Status = 'Captured';
        $pending->write();

        $this->assertEquals(91, $this->order->TotalPaid());
        $this->assertEquals(98, $this->order->TotalPaidOrAuthorized());
    }

    /**
     * Test pending payment check
     */
    public function testPendingPayments()
    {
        $this->assertTrue($this->order->HasPendingPayments());

        // capture the pending payment
        $pending = $this->order->Payments()->filter('Status', 'PendingCapture')->first();
        $pending->Status = 'Captured';
        $pending->write();

        $this->assertFalse($this->order->HasPendingPayments());
    }
}
