<?php

namespace SilverStripe\Omnipay\Tests\Model;

use SilverStripe\Dev\TestOnly;
use Omnipay\Common\AbstractGateway;

class TestOffsiteGateway extends AbstractGateway implements TestOnly
{
    public function getName()
    {
        return 'GatewayInfoTest_OffsiteGateway';
    }

    public function getDefaultParameters()
    {
        return [];
    }

    public function purchase(array $parameters = [])
    {
    }

    public function completePurchase(array $options = [])
    {
    }
}
