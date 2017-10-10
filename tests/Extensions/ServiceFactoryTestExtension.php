<?php

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\CaptureService;
use SilverStripe\Omnipay\Tests\Service\ServiceFactoryTestService;

class ServiceFactoryTestExtension extends Extension implements TestOnly
{
    // return some different service for testing
    public function createPurchaseService(Payment $payment)
    {
        return CaptureService::create($payment);
    }

    public function createTestService($payment)
    {
        return ServiceFactoryTestService::create($payment);
    }

    public function createAuthorizeService(Payment $payment)
    {
        return CaptureService::create($payment);
    }
}
