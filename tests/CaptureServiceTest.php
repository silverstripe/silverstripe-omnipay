<?php

use SilverStripe\Omnipay\Service\CaptureService;

/**
 * Test the capture service
 */
class CaptureServiceTest extends BaseNotificationServiceTest
{
    protected $gatewayMethod = 'capture';

    protected $fixtureIdentifier = 'payment6';

    protected $fixtureReceipt = 'authorizedPaymentReceipt';

    protected $startStatus = 'Authorized';

    protected $pendingStatus = 'PendingCapture';

    protected $endStatus = 'Captured';

    protected $successFromFixtureMessages = array(
        array( // response that was loaded from the fixture
            'ClassName' => 'AuthorizedResponse',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated Capture request
            'ClassName' => 'CaptureRequest',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated Capture response
            'ClassName' => 'CapturedResponse',
            'Reference' => 'authorizedPaymentReceipt'
        )
    );

    protected $successMessages = array(
        array( // the generated capture request
            'ClassName' => 'CaptureRequest',
            'Reference' => 'testThisRecipe123'
        ),
        array( // the generated capture response
            'ClassName' => 'CapturedResponse',
            'Reference' => 'testThisRecipe123'
        )
    );

    protected $failureMessages = array(
        array( // response that was loaded from the fixture
            'ClassName' => 'AuthorizedResponse',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated capture request
            'ClassName' => 'CaptureRequest',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated capture response
            'ClassName' => 'CaptureError',
            'Reference' => 'authorizedPaymentReceipt'
        )
    );

    protected $errorMessageClass = 'CaptureError';
    
    protected function getService(Payment $payment)
    {
        return CaptureService::create($payment);
    }
}
