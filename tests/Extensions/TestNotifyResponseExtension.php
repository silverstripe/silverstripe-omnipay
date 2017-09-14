<?php

namespace SilverStripe\Omnipay\Tests\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\Control\HTTPResponse;

class TestNotifyResponseExtension extends Extension implements TestOnly
{
    public function updateServiceResponse(ServiceResponse $serviceResponse)
    {
        if ($serviceResponse->isNotification()) {
            if ($serviceResponse->getPayment()->Gateway == 'FantasyGateway') {
                $httpResponse = new HTTPResponse('OK', 200);
                $httpResponse->addHeader('X-FantasyGateway-Api', 'apikey12345');
            } else {
                $httpResponse = new HTTPResponse('SUCCESS', 200);
            }

            $serviceResponse->setHttpResponse($httpResponse);
        }
    }
}
