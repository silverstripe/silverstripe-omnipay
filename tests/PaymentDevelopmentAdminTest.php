<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Omnipay\Admin\PaymentDevelopmentAdmin;
use SilverStripe\Omnipay\Model\Payment;

class PaymentDevelopmentAdminTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected $autoFollowRedirection = false;

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        Payment::config()->set('allowed_gateways', ['Manual', 'Dummy']);
        $this->originalEnvironment = Injector::inst()->get(Kernel::class)->getEnvironment();
    }

    protected function tearDown(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment($this->originalEnvironment);
        parent::tearDown();
    }

    public function testCanInitOnLive(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment('live');
        $controller = PaymentDevelopmentAdmin::create();

        $this->assertFalse($controller->canInit(), 'Anonymous users must not access dev/payment on live');

        $this->logInWithPermission('ALL_DEV_ADMIN');
        $this->assertTrue($controller->canInit());
    }

    public function testCanInitInDev(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment('dev');
        $this->assertTrue(PaymentDevelopmentAdmin::create()->canInit());
    }

    public function testRegisteredWithDevelopmentAdmin(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment('dev');
        ob_start();
        $response = $this->get('dev/payment');
        ob_end_clean();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAnonymousRequestOnLiveIsNotServed(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment('live');
        ob_start();
        $response = $this->get('dev/payment');
        $output = ob_get_clean();
        $this->assertNotEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('<table', $output . $response->getBody());
    }
}
