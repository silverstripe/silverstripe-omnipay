<?php

namespace SilverStripe\Omnipay\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Tests\Extensions\ServiceFactoryTestExtension;
use SilverStripe\Omnipay\Tests\Extensions\ServiceFactoryTestExtensionTwo;
use SilverStripe\Omnipay\Tests\Service\ServiceFactoryTestService;

class ServiceFactoryTest extends PaymentTest
{
    private static $dependencies = [
        ServiceFactoryTestTestService::class
    ];

    /**
     * @expectedException \SilverStripe\Omnipay\Exception\InvalidConfigurationException
     */
    public function testDefaultServices()
    {
        $payment = Payment::create()
            ->setGateway("PaymentExpress_PxPay")
            ->setAmount(123)
            ->setCurrency("GBP");

        //$this->setExpectException(InvalidConfigurationException::class);
        $this->assertInstanceOf(
            'SilverStripe\Omnipay\Service\AuthorizeService',
            $this->factory->getService($payment, ServiceFactory::INTENT_AUTHORIZE),
            'Intent "authorize" should return an instance of "AuthorizeService".'
        );

        $this->assertInstanceOf(
            'SilverStripe\Omnipay\Service\PurchaseService',
            $this->factory->getService($payment, ServiceFactory::INTENT_PAYMENT),
            'Intent "payment" must return a PurchaseService when gateway doesn\'t use authorize.'
        );

        Config::modify()->merge(GatewayInfo::class, 'PaymentExpress_PxPay', [
            'use_authorize' => true
        ]);

        $this->assertInstanceOf(
            'SilverStripe\Omnipay\Service\AuthorizeService',
            $this->factory->getService($payment, ServiceFactory::INTENT_PAYMENT),
            'Intent "payment" must return a AuthorizeService when gateway is configured to use authorize.'
        );

        // This will throw an exception, because there's no service for the intent "undefined"
        $this->expectException('\SilverStripe\Omnipay\Exception\InvalidConfigurationException');
        $this->factory->getService($this->payment, 'undefined');
    }

    public function testCustomService()
    {
        Config::modify()->merge(ServiceFactory::class, 'services', [
            'purchase' => ServiceFactoryTestService::class
        ]);

        $this->assertInstanceOf(
            ServiceFactoryTestService::class,
            $this->factory->getService($this->payment, ServiceFactory::INTENT_PURCHASE),
            'The factory should return the configured service instead of the default one.'
        );

        ServiceFactory::add_extension(ServiceFactoryTestExtension::class);
        ServiceFactory::add_extension(ServiceFactoryTestExtensionTwo::class);

        // create a new factory instance that uses the new extensions
        $factory = ServiceFactory::create();

        $factory->getService($this->payment, ServiceFactory::INTENT_PURCHASE);

        // the extension will now take care of creating the purchase service
        $this->assertInstanceOf(
            'SilverStripe\Omnipay\Service\CaptureService',
            $factory->getService($this->payment, ServiceFactory::INTENT_PURCHASE),
            'The factory should return the service generated by the extension.'
        );

        // by having a correctly named method on the extension, 'test' is a valid intent
        $this->assertInstanceOf(
            ServiceFactoryTestService::class,
            $factory->getService($this->payment, 'test'),
            'The extension should create a ServiceFactoryTest_TestService instance for the "test" intent.'
        );

        $catched = null;
        try {
            // this should throw an exception since two extensions try to create a service
            $factory->getService($this->payment, ServiceFactory::INTENT_AUTHORIZE);
        } catch (InvalidConfigurationException $ex) {
            $catched = $ex;
        }

        $this->assertInstanceOf(
            'SilverStripe\Omnipay\Exception\InvalidConfigurationException',
            $catched,
            'When two extensions create service instances, an exception should be raised.'
        );
    }
}
