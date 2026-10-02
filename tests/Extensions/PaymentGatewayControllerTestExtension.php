<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Model\Payment;

/**
 * @extends Extension<static>
 */
class PaymentGatewayControllerTestExtension extends Extension implements TestOnly
{
    public function updatePaymentFromRequest(HTTPRequest $httpRequest, $gateway)
    {
        if ($gateway === 'PaymentExpress_PxPay') {
            return Payment::get()->filter('Identifier', $httpRequest->getVar('id'))->first();
        }

        return null;
    }

    public function updatePaymentActionFromRequest(&$action, Payment $payment, HTTPRequest $httpRequest): void
    {
        if ($payment->Gateway == 'PaymentExpress_PxPay' && $httpRequest->getVar('action')) {
            $action = $httpRequest->getVar('action');
        }
    }
}
