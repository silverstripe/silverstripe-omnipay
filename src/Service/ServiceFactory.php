<?php

namespace SilverStripe\Omnipay\Service;

use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Config\Configurable;

class ServiceFactory
{
    use Injectable;
    use Extensible;
    use Configurable;

    /**
     * @var array $services
     *
     * @config
     */
    private static $services = [];

    /*
     * Different constants for commonly used intents.
     */
    const INTENT_AUTHORIZE  = "authorize";
    const INTENT_CREATECARD = "createcard";
    const INTENT_PURCHASE   = "purchase";
    const INTENT_REFUND     = "refund";
    const INTENT_VOID       = "void";
    const INTENT_CAPTURE    = "capture";
    const INTENT_PAYMENT    = "payment";

    /**
     * Create a service for the given payment and intent.
     *
     * This method will look for a method named `create{Intent}Service`, where
     * "{Intent}" has to be substituted with the intent. So an intent "authorize" will look for a method named
     * "createAuthorizeService". The method will be called on extensions first and then on this class itself, given
     * that no extension returned a valid PaymentService instance.
     *
     * If the method didn't return an instance, this will fall back to the services configuration.
     *
     * @param Payment $payment the payment instance
     * @param string $intent the intent of the service.
     *
     * @return PaymentService
     * @throws InvalidConfigurationException when creation of the service failed due to misconfiguration
     */
    public function getService(Payment $payment, $intent)
    {
        $method = 'create' . ucfirst($intent) . 'Service';
        $values = $this->extend($method, $payment);

        if (count($values) > 1) {
            throw new InvalidConfigurationException("Multiple extensions are trying to create a service for '$intent'");
        }

        if (count($values) === 1 && $values[0] instanceof PaymentService) {
            return $values[0];
        }

        if (method_exists($this, $method)) {
            return $this->$method($payment);
        }

        $serviceMap = $this->config()->get('services');

        if (is_array($serviceMap) && isset($serviceMap[$intent])) {
            $serviceType = $serviceMap[$intent];

            if (is_subclass_of($serviceType, PaymentService::class)) {
                return $serviceType::create($payment);
            }
        }

        throw new InvalidConfigurationException("Unable to create a service for '$intent'");
    }

    /**
     * Create a payment service. This will either return an AuthorizeService or PurchaseService, depending on
     * the gateway config.
     *
     * @param Payment $payment
     * @return PaymentService
     *
     * @throws InvalidConfigurationException
     */
    protected function createPaymentService(Payment $payment)
    {
        return $this->getService(
            $payment,
            GatewayInfo::shouldUseAuthorize($payment->Gateway)
                ? ServiceFactory::INTENT_AUTHORIZE
                : ServiceFactory::INTENT_PURCHASE
        );
    }
}
