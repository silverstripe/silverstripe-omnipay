<?php

/**
 * Test the Payable extension
 */
class PayableTest extends SapphireTest
{
    /** @var Test_Order */
    protected $order;
    protected static $fixture_file = 'PayableTest.yml';
    protected $extraDataObjects = array('Test_Order');

    public function setUpOnce()
    {
        Payment::add_extension('Test_PaymentExtension');

        parent::setUpOnce();
    }

    public function tearDownOnce()
    {
        parent::tearDownOnce();

        Payment::remove_extension('Test_PaymentExtension');
    }

    public function setUp()
    {
        parent::setUp();
        // don't log test payments to file
        Config::inst()->update('Payment', 'file_logging', 0);
        $this->order = $this->objFromFixture('Test_Order', 'order1');
    }

    /**
     * Test the CMS fields added via extension
     */
    public function testCMSFields()
    {
        $order = new Test_Order();
        $fields = $order->getCMSFields();

        $this->assertTrue($fields->hasTabSet());

        /** @var GridField $gridField */
        $gridField = $fields->fieldByName('Root.Payments.Payments');

        $this->assertInstanceOf('GridField', $gridField);

        // Check the actions/buttons that should be in place
        $this->assertNotNull($gridField->getConfig()->getComponentByType(
            'GridFieldEditButton'
        ));
        $this->assertNotNull($gridField->getConfig()->getComponentByType(
            'SilverStripe\Omnipay\Admin\GridField\GridFieldCaptureAction'
        ));
        $this->assertNotNull($gridField->getConfig()->getComponentByType(
            'SilverStripe\Omnipay\Admin\GridField\GridFieldRefundAction'
        ));
        $this->assertNotNull($gridField->getConfig()->getComponentByType(
            'SilverStripe\Omnipay\Admin\GridField\GridFieldVoidAction'
        ));

        // check the actions buttons that should be removed
        $this->assertNull($gridField->getConfig()->getComponentByType('GridFieldAddNewButton'));
        $this->assertNull($gridField->getConfig()->getComponentByType('GridFieldDeleteAction'));
        $this->assertNull($gridField->getConfig()->getComponentByType('GridFieldFilterHeader'));
        $this->assertNull($gridField->getConfig()->getComponentByType('GridFieldPageCount'));
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

class Test_Order extends DataObject implements TestOnly
{
    private static $extensions = array(
        'Payable'
    );
}

class Test_PaymentExtension extends DataExtension implements TestOnly
{
    private static $has_one = array(
        'Test_Order' => 'Test_Order'
    );
}
