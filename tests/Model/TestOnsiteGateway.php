<?php

namespace SilverStripe\Omnipay\Tests\Model;

use SilverStripe\Dev\TestOnly;
use Omnipay\Common\AbstractGateway;

class TestOnsiteGateway extends AbstractGateway implements TestOnly
{
    public function getName()
    {
        return 'TestOnsite';
    }

    public function getDefaultParameters()
    {
        return [];
    }

    public function purchase(array $parameters = [])
    {
    }
}
