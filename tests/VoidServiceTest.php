<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\VoidService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;
use SilverStripe\Omnipay\Model\Message;

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
            'ClassName' => Message\AuthorizedResponse::class,
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated void request
            'ClassName' => Message\VoidRequest::class,
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated void response
            'ClassName' => Message\VoidedResponse::class,
            'Reference' => 'authorizedPaymentReceipt'
        )
    );

    protected $successMessages = array(
        array( // the generated void request
            'ClassName' => Message\VoidRequest::class,
            'Reference' => 'testThisRecipe123'
        ),
        array( // the generated void response
            'ClassName' => Message\VoidedResponse::class,
            'Reference' => 'testThisRecipe123'
        )
    );

    protected $failureMessages = array(
        array( // response that was loaded from the fixture
            'ClassName' => Message\AuthorizedResponse::class,
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated void request
            'ClassName' => Message\VoidRequest::class,
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array( // the generated void error
            'ClassName' => Message\VoidError::class,
            'Reference' => 'authorizedPaymentReceipt'
        )
    );

    protected $notificationFailureMessages = array(
        array(
            'ClassName' => Message\AuthorizedResponse::class,
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array(
            'ClassName' => Message\VoidRequest::class,
            'Reference' => 'authorizedPaymentReceipt'
        ),
        array(
            'ClassName' => Message\NotificationError::class,
            'Reference' => 'authorizedPaymentReceipt'
        )
    );

    protected $errorMessageClass = Message\VoidError::class;

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
        $this->logInWithPermission('VOID_PAYMENTS');
        VoidService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    public function tearDown()
    {
        parent::tearDown();
        VoidService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment)
    {
        return VoidService::create($payment);
    }
}
