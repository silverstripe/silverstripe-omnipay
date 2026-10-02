<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\AuthorizeService;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\Omnipay\Service\VoidService;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;

/**
 * Test the void service
 */
class VoidServiceTest extends FunctionalTest
{
    use BaseNotificationServiceTestTrait;
    use PaymentTestTrait;

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected string $gatewayMethod = 'void';

    protected string $fixtureIdentifier = 'payment6';

    protected string $fixtureReceipt = 'authorizedPaymentReceipt';

    protected string $startStatus = 'Authorized';

    protected string $pendingStatus = 'PendingVoid';

    protected string $endStatus = 'Void';

    protected array $successFromFixtureMessages = [
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

    protected array $successMessages = [
        [
            'Type' => VoidService::MESSAGE_VOID_REQUEST,
            'Reference' => 'testThisRecipe123'
        ],
        [
            'Type' => VoidService::MESSAGE_VOIDED_RESPONSE,
            'Reference' => 'testThisRecipe123'
        ]
    ];

    protected array $failureMessages = [
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

    protected array $notificationFailureMessages = [
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

    protected string $errorMessageType = VoidService::MESSAGE_VOID_ERROR;

    protected array $successPaymentExtensionHooks = [
        'onVoid'
    ];

    protected array $initiateServiceExtensionHooks = [
        'onBeforeVoid',
        'onAfterVoid',
        'onAfterSendVoid',
        'updateServiceResponse'
    ];

    protected array $initiateFailedServiceExtensionHooks = [
        'onBeforeVoid',
        'onAfterVoid',
        'updateServiceResponse'
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->payment = Payment::create()
            ->setGateway("Dummy")
            ->setAmount(1222)
            ->setCurrency("GBP");

        $this->logInWithPermission('VOID_PAYMENTS');

        VoidService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        VoidService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment): PaymentService
    {
        return VoidService::create($payment);
    }
}
