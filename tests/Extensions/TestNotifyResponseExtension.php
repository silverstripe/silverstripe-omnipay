<?php

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\Control\HTTPResponse;

/**
 * @extends Extension<static>
 */
class TestNotifyResponseExtension extends Extension implements TestOnly
{
    public function updateServiceResponse(ServiceResponse $serviceResponse): void
    {
        if ($serviceResponse->isNotification()) {
            if ($serviceResponse->getPayment()->Gateway == 'FantasyGateway') {
                $httpResponse = HTTPResponse::create('OK', 200);
                $httpResponse->addHeader('X-FantasyGateway-Api', 'apikey12345');
            } else {
                $httpResponse = HTTPResponse::create('SUCCESS', 200);
            }

            $serviceResponse->setHttpResponse($httpResponse);
        }
    }
}
