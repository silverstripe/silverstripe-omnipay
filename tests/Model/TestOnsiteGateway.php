<?php

namespace SilverStripe\Omnipay\Tests\Model;

use SilverStripe\Dev\TestOnly;
use Omnipay\Common\AbstractGateway;

class TestOnsiteGateway extends AbstractGateway implements TestOnly
{
    public function getName()
    {
        return 'GatewayInfoTest_OnsiteGateway';
    }

    public function getDefaultParameters()
    {
        return [];
    }

    public function purchase(array $parameters = [])
    {
    }
}
