<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\AuthorizeService;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\VoidService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;

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

    protected $successFromFixtureMessages = [
        [
            'Type' => AuthorizeService::MESSAGE_AUTHORIZED_RESPONSE,
            'Reference' => 'authorizedPaymentReceipt'
        ],
        [
            'Type' => VoidService::MESSAGE_VOID_REQUEST,
            'Reference' => 'authorizedPaymentReceipt'
        ],
        [
            'Type' => VoidService::MESSAGE_VOIDED_RESPONSE,
            'Reference' => 'authorizedPaymentReceipt'
        ]
    ];

    protected $successMessages = [
        [
            'Type' => VoidService::MESSAGE_VOID_REQUEST,
            'Reference' => 'testThisRecipe123'
        ],
        [
            'Type' => VoidService::MESSAGE_VOIDED_RESPONSE,
            'Reference' => 'testThisRecipe123'
        ]
    ];

    protected $failureMessages = [
        [
            'Type' => AuthorizeService::MESSAGE_AUTHORIZED_RESPONSE,
            'Reference' => 'authorizedPaymentReceipt'
        ],
        [
            'Type' => VoidService::MESSAGE_VOID_REQUEST,
            'Reference' => 'authorizedPaymentReceipt'
        ],
        [
            'Type' => VoidService::MESSAGE_VOID_ERROR,
            'Reference' => 'authorizedPaymentReceipt'
        ]
    ];

    protected $notificationFailureMessages = [
        [
            'Type' => AuthorizeService::MESSAGE_AUTHORIZED_RESPONSE,
            'Reference' => 'authorizedPaymentReceipt'
        ],
        [
            'Type' => VoidService::MESSAGE_VOID_REQUEST,
            'Reference' => 'authorizedPaymentReceipt'
        ],
        [
            'Type' => PaymentService::MESSAGE_NOTIFICATION_ERROR,
            'Reference' => 'authorizedPaymentReceipt'
        ]
    ];

    protected $errorMessageType = VoidService::MESSAGE_VOID_ERROR;

    protected $successPaymentExtensionHooks = [
        'onVoid'
    ];

    protected $initiateServiceExtensionHooks = [
        'onBeforeVoid',
        'onAfterVoid',
        'onAfterSendVoid',
        'updateServiceResponse'
    ];

    protected $initiateFailedServiceExtensionHooks = [
        'onBeforeVoid',
        'onAfterVoid',
        'updateServiceResponse'
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->logInWithPermission('VOID_PAYMENTS');

        VoidService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        VoidService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment)
    {
        return VoidService::create($payment);
    }
}
