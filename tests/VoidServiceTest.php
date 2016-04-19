<?php

use SilverStripe\Omnipay\Service\VoidService;

/**
 * Test the void service
 */
class VoidServiceTest extends BaseNotificationServiceTest
{
    protected $gatewayMethod = 'void';

    protected $fixtureIdentifier = 'payment6';

    protected $fixtureReceipt = 'authorizedPaymentReceipt';

    protected $startStatus = 'Authorized';

    protected $pendingStatus = 'PendingVoid';

    protected $endStatus = 'Void';

    protected $successFromFixtureMessages = array(
        array( // response that was loaded from the fixture
            'ClassName' => 'AuthorizedResponse',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated void request
            'ClassName' => 'VoidRequest',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated void response
            'ClassName' => 'VoidedResponse',
            'Reference' => 'authorizedPaymentReceipt'
        )
    );

    protected $successMessages = array(
        array( // the generated void request
            'ClassName' => 'VoidRequest',
            'Reference' => 'testThisRecipe123'
        ),
        array( // the generated void response
            'ClassName' => 'VoidedResponse',
            'Reference' => 'testThisRecipe123'
        )
    );

    protected $failureMessages = array(
        array( // response that was loaded from the fixture
            'ClassName' => 'AuthorizedResponse',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated void request
            'ClassName' => 'VoidRequest',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated void error
            'ClassName' => 'VoidError',
            'Reference' => 'authorizedPaymentReceipt'
        )
    );

    protected $errorMessageClass = 'VoidError';

    protected function getService(Payment $payment)
    {
        return VoidService::create($payment);
    }
}
