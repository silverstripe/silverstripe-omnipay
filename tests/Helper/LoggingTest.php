<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Helper;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Helper\Logging;

class LoggingTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testSanitizeIsCaseInsensitiveAndReplacesNestedValues(): void
    {
        Config::modify()->set(Logging::class, 'logStyle', Logging::LOGSTYLE_VERBOSE);

        $result = Logging::prepareForLogging([
            'Token' => 'tok_123',
            'CardReference' => 'card_456',
            'Amount' => '10.00',
            'Parameters' => [
                'card' => [
                    'number' => '4111111111111111',
                    'expiryMonth' => '12',
                    'billingName' => 'Jane Doe',
                ],
                'transactionId' => 'abc',
            ],
        ]);

        $this->assertSame(Logging::SANITIZED, $result['Token']);
        $this->assertSame(Logging::SANITIZED, $result['CardReference']);
        $this->assertSame('10.00', $result['Amount']);
        $this->assertSame(Logging::SANITIZED, $result['Parameters']['card']);
        $this->assertSame('abc', $result['Parameters']['transactionId']);
    }

    public function testFullLogStyleOnlyHonouredInDev(): void
    {
        Config::modify()->set(Logging::class, 'logStyle', Logging::LOGSTYLE_FULL);
        $kernel = Injector::inst()->get(Kernel::class);
        $original = $kernel->getEnvironment();

        try {
            $kernel->setEnvironment('test');
            $this->assertSame(Logging::SANITIZED, Logging::prepareForLogging(['token' => 'secret'])['token']);

            $kernel->setEnvironment('dev');
            $this->assertSame('secret', Logging::prepareForLogging(['token' => 'secret'])['token']);
        } finally {
            $kernel->setEnvironment($original);
        }
    }
}
