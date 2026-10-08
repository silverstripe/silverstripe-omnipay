<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Model;

use Omnipay\Common\AbstractGateway;
use SilverStripe\Dev\TestOnly;

/**
 * Offsite gateway that confirms payments via notifications only and
 * doesn't implement completePurchase or completeAuthorize.
 */
class TestNotifyOnlyGateway extends AbstractGateway implements TestOnly
{
    public function getName()
    {
        return 'TestNotifyOnly';
    }

    public function getDefaultParameters()
    {
        return [];
    }

    public function purchase(array $parameters = [])
    {
    }

    public function authorize(array $parameters = [])
    {
    }

    public function acceptNotification()
    {
    }
}
