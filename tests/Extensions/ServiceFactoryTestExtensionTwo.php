<?php

namespace SilverStripe\Omnipay\Tests\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Model\Payment;

class ServiceFactoryTestExtensionTwo extends Extension implements TestOnly
{
    public function createAuthorizeService(Payment $payment)
    {
        return CaptureService::create($payment);
    }
}
