<?php

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Model\Payment;

class PaymentGatewayControllerTestExtension extends Extension implements TestOnly
{
    public function updatePaymentFromRequest(HTTPRequest $request, $gateway)
    {
        if ($gateway === 'PaymentExpress_PxPay') {
            return Payment::get()->filter('Identifier', $request->getVar('id'))->first();
        }
        return null;
    }

    public function updatePaymentActionFromRequest(&$action, Payment $payment, HTTPRequest $request)
    {
        if ($payment->Gateway == 'PaymentExpress_PxPay' && $request->getVar('action')) {
            $action = $request->getVar('action');
        }
    }
}
