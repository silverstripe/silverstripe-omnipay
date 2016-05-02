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

    protected $notificationFailureMessages = array(
        array(
            'ClassName' => 'AuthorizedResponse',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array(
            'ClassName' => 'VoidRequest',
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array(
            'ClassName' => 'NotificationError',
            'Reference' => 'authorizedPaymentReceipt'
        )
    );

    protected $errorMessageClass = 'VoidError';

    protected $successPaymentExtensionHooks = array(
        'onVoid'
    );

    protected $initiateServiceExtensionHooks = array(
        'onBeforeVoid',
        'onAfterVoid',
        'onAfterSendVoid',
        'updateServiceResponse'
    );

    protected $initiateFailedServiceExtensionHooks = array(
        'onBeforeVoid',
        'onAfterVoid',
        'updateServiceResponse'
    );

    public function setUp()
    {
        parent::setUp();
        VoidService::add_extension('PaymentTest_ServiceExtensionHooks');
    }

    public function tearDown()
    {
        parent::tearDown();
        VoidService::remove_extension('PaymentTest_ServiceExtensionHooks');
    }

    protected function getService(Payment $payment)
    {
        return VoidService::create($payment);
    }
}
