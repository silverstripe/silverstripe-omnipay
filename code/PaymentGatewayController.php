<?php

namespace SilverStripe\Omnipay;

use SilverStripe\Omnipay\Service\ServiceFactory;

/**
 * Payment Gateway Controller
 *
 * This controller handles redirects from gateway servers, and also behind-the-scenes
 * requests that gateway servers to notify our application of successful payment.
 *
 * @package payment
 */
class PaymentGatewayController extends \Controller
{
    private static $allowed_actions = [
        'gateway'
    ];
    
    private static $url_handlers = [
        'gateway/$Gateway!/$Status/$Identifier' => 'gateway',
        '$Identifier/$Status/$ReturnURL' => 'index',
    ];

    /**
     * Generate an absolute url for gateways to return to, or send requests to.
     * @param  string $status the intended action of the gateway
     * @param  string $identifier the unique payment id
     * @return string the resulting redirect url
     */
    public static function getEndpointUrl($status, $identifier, $gateway = null)
    {   
        // Does the selected gateway allow static routes
        if ($gateway && GatewayInfo::getConfigSetting($gateway, 'use_static_route')) {
            $url = \Controller::join_links(
                'paymentendpoint',
                'gateway',
                $gateway,
                $status,
                $identifier
            );
        } else {
            $url = \Controller::join_links(
                'paymentendpoint',
                $identifier,
                $status
            );
        }

        return \Director::absoluteURL($url);
    }

    /**
     * Generate an absolute url for gateways to return to, or send requests to.
     * @param  string             $action      the intended action of the gateway
     * @param  string             $returnurl   the application url to re-redirect to
     * @return string                          the resulting redirect url
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use getEndpointUrl
     * @codeCoverageIgnore
     */
    public static function get_endpoint_url($action, $identifier)
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use getEndpointUrl');
        return self::getEndpointUrl($action, $identifier);
    }

    /**
     * Find the intent of the current payment
     *
     * @param string $status The status of the payment
     * @return string | null
     */
    protected function getPaymentIntent($status)
    {
        switch ($status) {
            // We have to check for both states here, since the notification might come in before the gateway returns
            // if that's the case, the status of the payment will already be set to 'Authorized'
            case 'PendingAuthorization':
            case 'Authorized':
                $intent = ServiceFactory::INTENT_AUTHORIZE;
                break;
            case 'PendingCreateCard':
            case 'CardCreated':
                $intent = ServiceFactory::INTENT_CREATECARD;
                break;
            case 'PendingCapture':
                $intent = ServiceFactory::INTENT_CAPTURE;
                break;
            // Both states have to be checked (see explanation with 'Authorized')
            case 'PendingPurchase':
            case 'Captured':
                $intent = ServiceFactory::INTENT_PURCHASE;
                break;
            case 'PendingRefund':
                $intent = ServiceFactory::INTENT_REFUND;
                break;
            case 'PendingVoid':
                $intent = ServiceFactory::INTENT_VOID;
                break;
            default:
                $intent = null;
        }

        return $intent;
    }

    /**
     * Get the response from the @Link SilverStripe\Omnipay\Service\PaymentService
     * depending on the payment status provided.
     *
     * @param PaymentService $service The payment service we are using
     * @param string $status The string identifier of the ServiceResponse
     * @return void
     */
    protected function getServiceResponse($service, $status)
    {
        switch ($status) {
            case "complete":
                $return = $service->complete();
                break;
            case "notify":
                $return = $service->complete(array(), true);
                break;
            case "cancel":
                $return = $service->cancel();
                break;
            default:
                $return = null;
        }

        return $return;
    }

    /**
     * The main action for handling all requests.
     * It will redirect back to the application in all cases,
     * but will not update the Payment/Transaction models if they are not found,
     * or allowed to be updated.
     */
    public function index()
    {
        $response = null;
        $payment = $this->getPaymentFromIdentifier($this->request->param('Identifier'));

        if (!$payment) {
            return $this->httpError(404, _t('Payment.NotFound', 'Payment could not be found.'));
        }

        $intent = $this->getPaymentIntent($payment->Status);

        if (!$intent) {
            return $this->httpError(403, _t('Payment.InvalidStatus', 'Invalid/unhandled payment status'));
        }

        $service = ServiceFactory::create()->getService($payment, $intent);
        $service_response = $this->getServiceResponse($service, $this->request->param('Status'));

        if (!$service_response) {
            return $this->httpError(404, _t('Payment.InvalidUrl', 'Invalid payment url.'));
        }
        
        $response = $service_response->redirectOrRespond();
        
        return $response;
    }

    /**
     * Action used for handling static gateway requests
     * (use this if your payment gateway doesnt handle
     * dynamic callback URLs) 
     */
    public function gateway()
    {
        $response = null;
        $gateway = $this->request->param("Gateway");
        $identifier = null;

        // Does the selected gateway allow static routes
        if (!GatewayInfo::getConfigSetting($gateway, 'use_static_route')) {
            return $this->httpError(404, _t('Payment.InvalidUrl', 'Invalid payment url.'));
        }

        $identifier = $this->getIdentifierFromRequest($this->request,$gateway);

        if (!$identifier) {
            return $this->httpError(404, _t('Payment.NotFound', 'Payment could not be found.'));
        }

        $payment = $this->getPaymentFromIdentifier($identifier);

        if (!$payment) {
            return $this->httpError(404, _t('Payment.NotFound', 'Payment could not be found.'));
        }

        $intent = $this->getPaymentIntent($payment->Status);

        if (!$intent) {
            return $this->httpError(403, _t('Payment.InvalidStatus', 'Invalid/unhandled payment status'));
        }

        $service = ServiceFactory::create()->getService($payment, $intent);
        $service_response = $this->getServiceResponse($service, $this->request->param('Status'));

        if (!$service_response) {
            return $this->httpError(404, _t('Payment.InvalidUrl', 'Invalid payment url.'));
        }

        $response = $service_response->redirectOrRespond();

        return $response;
    }

    /**
     * Attempt to get the the payment according to the identifier
     * provided by the payment gateway.
     * 
     * Due to the many possible ways this can be retrieved, it is
     * up to the implementer to extend this function and write their
     *  
     * 
     * @param SS_HTTPRequest $request A SilverStripe request object
     * @param string $gateway The identifier of the payment
     * @return \Payment the payment
     */
    private function getIdentifierFromRequest(\SS_HTTPRequest $request, $gateway)
    {
        $ident = $this->request->param('Identifier');

        $this->extend("updateIdentifierFromRequest", $ident, $request, $gateway);
        
        return $ident;
    }

    /**
     * Get the the payment according to the identifer given in the url
     * 
     * @param string $ident The identifier of the payment
     * @return \Payment the payment
     */
    private function getPaymentFromIdentifier($ident)
    {
        return \Payment::get()
                ->filter('Identifier', $ident)
                ->filter('Identifier:not', "")
                ->first();
    }
}
