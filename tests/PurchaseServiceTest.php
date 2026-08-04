<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Omnipay\Service\PurchaseService;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Tests\Extensions\PaymentTestServiceExtensionHooks;

class PurchaseServiceTest extends BasePurchaseServiceTest
{
    protected $completeStatus = 'Captured';
    protected $pendingStatus = 'PendingPurchase';

    protected $omnipayMethod = 'purchase';
    protected $omnipayCompleteMethod = 'completePurchase';

    protected $onsiteSuccessMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE]
    ];

    protected $onsiteFailMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASE_ERROR]
    ];

    protected $failMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_ERROR]
    ];

    protected $offsiteSuccessMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE],
        ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASED_RESPONSE]
    ];

    protected $offsiteFailMessages = [
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_PURCHASE_REDIRECT_RESPONSE],
        ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_REQUEST],
        ['Type' => PurchaseService::MESSAGE_COMPLETE_PURCHASE_ERROR]
    ];

    protected $failureMessageType = PurchaseService::MESSAGE_COMPLETE_PURCHASE_ERROR;

    protected $paymentId = 'UNIQUEHASH23q5123tqasdf';

    protected $successPaymentExtensionHooks = [
        'onCaptured'
    ];

    protected $notifyPaymentExtensionHooks = [
        'onAwaitingCaptured'
    ];

    protected $initiateServiceExtensionHooks = [
        'onBeforePurchase',
        'onAfterPurchase',
        'onAfterSendPurchase',
        'updateServiceResponse'
    ];

    protected $initiateFailedServiceExtensionHooks = [
        'onBeforePurchase',
        'onAfterPurchase',
        'updateServiceResponse'
    ];

    protected $completeServiceExtensionHooks = [
        'onBeforeCompletePurchase',
        'onAfterCompletePurchase',
        'updateServiceResponse'
    ];

    public function setUp(): void
    {
        parent::setUp();

        PurchaseService::add_extension(PaymentTestServiceExtensionHooks::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        PurchaseService::remove_extension(PaymentTestServiceExtensionHooks::class);
    }

    protected function getService(Payment $payment)
    {
        return PurchaseService::create($payment);
    }

    public function testOnBeforePurchaseCanMutateGatewayData(): void
    {
        $items = [
            [
                'name' => 'item1',
                'quantity' => 2,
                'price' => '10.00',
                'description' => 'some description',
            ],
            [
                'name' => 'item2',
                'quantity' => 1,
                'price' => '50.00',
                'description' => 'some description',
            ],
        ];

        $stubRequest = $this->stubRequest();
        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->onlyMethods(['getName'])
            ->addMethods(['supportsPurchase', 'purchase'])
            ->getMock();
        $stubGateway->method('supportsPurchase')->willReturn(true);
        $stubGateway->expects($this->once())
            ->method('purchase')
            ->with($this->callback(function (array $gatewayData) use ($items) {
                return isset($gatewayData['items']) && $gatewayData['items'] === $items;
            }))
            ->willReturn($stubRequest);

        $service = $this->getService($this->payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));
        $service->initiate();
    }

    public function testOnBeforeCompletePurchaseCanMutateGatewayData(): void
    {
        $items = [
            [
                'name' => 'item1',
                'quantity' => 2,
                'price' => '10.00',
                'description' => 'some description',
            ],
            [
                'name' => 'item2',
                'quantity' => 1,
                'price' => '50.00',
                'description' => 'some description',
            ],
        ];

        $stubRequest = $this->stubRequest();
        $stubGateway = $this->getMockBuilder('Omnipay\Common\AbstractGateway')
            ->onlyMethods(['getName'])
            ->addMethods(['supportsCompletePurchase', 'completePurchase'])
            ->getMock();
        $stubGateway->method('supportsCompletePurchase')->willReturn(true);
        $stubGateway->expects($this->once())
            ->method('completePurchase')
            ->with($this->callback(function (array $gatewayData) use ($items) {
                return isset($gatewayData['items']) && $gatewayData['items'] === $items;
            }))
            ->willReturn($stubRequest);

        $payment = $this->payment;
        $payment->Status = $this->pendingStatus;
        $service = $this->getService($payment);
        $service->setGatewayFactory($this->stubGatewayFactory($stubGateway));
        $service->complete();
    }
}
