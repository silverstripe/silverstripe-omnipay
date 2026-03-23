<?php

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Tests\Model\TestOrder;

class TestPaymentExtension extends Extension implements TestOnly
{
    /**
     * Static has_one is not merged from extensions in tests/ when ExtensionMiddleware
     * reads extension config with EXCLUDE_EXTRA_SOURCES (SilverStripe 6).
     */
    public static function get_extra_config($class, $extensionClass, $extensionArgs)
    {
        return [
            'has_one' => [
                'Test_Order' => TestOrder::class
            ]
        ];
    }
}
