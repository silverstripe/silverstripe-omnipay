<?php

use SilverStripe\Omnipay\Service\RefundService;

/**
 * Test the refund service
 */
class RefundServiceTest extends BaseNotificationServiceTest
{
    protected $gatewayMethod = 'refund';

    protected $fixtureIdentifier = 'payment3';

    protected $fixtureReceipt = 'paymentReceipt';

    protected $startStatus = 'Captured';

    protected $pendingStatus = 'PendingRefund';

    protected $endStatus = 'Refunded';

    protected $successFromFixtureMessages = array(
        array( // response that was loaded from the fixture
            'ClassName' => 'PurchasedResponse',
            'Reference' => 'paymentReceipt'
        ),
        array( // the generated refund request
            'ClassName' => 'RefundRequest',
            'Reference' => 'paymentReceipt'
        ),
        array( // the generated refund response
            'ClassName' => 'RefundedResponse',
            'Reference' => 'paymentReceipt'
        )
    );

    protected $successMessages = array(
        array( // the generated refund request
            'ClassName' => 'RefundRequest',
            'Reference' => 'testThisRecipe123'
        ),
        array( // the generated refund response
            'ClassName' => 'RefundedResponse',
            'Reference' => 'testThisRecipe123'
        )
    );

    protected $failureMessages = array(
        array( // response that was loaded from the fixture
            'ClassName' => 'PurchasedResponse',
            'Reference' => 'paymentReceipt'
        ),
        array( // the generated refund request
            'ClassName' => 'RefundRequest',
            'Reference' => 'paymentReceipt'
        ),
        array( // the generated refund response
            'ClassName' => 'RefundError',
            'Reference' => 'paymentReceipt'
        )
    );

    protected $errorMessageClass = 'RefundError';

    protected function getService(Payment $payment)
    {
        return RefundService::create($payment);
    }
}
