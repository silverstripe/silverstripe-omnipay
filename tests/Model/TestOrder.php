<?php

namespace SilverStripe\Omnipay\Tests\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Omnipay\Extensions\Payable;

class TestOrder extends DataObject implements TestOnly
{
    private static $extensions = [
        Payable::class
    ];
}
