<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\CaptureService;

/**
 * @extends Extension<static>
 */
class ServiceFactoryTestExtensionTwo extends Extension implements TestOnly
{
    public function createAuthorizeService(Payment $payment)
    {
        return CaptureService::create($payment);
    }
}
