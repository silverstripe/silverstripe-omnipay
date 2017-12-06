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
        'gateway/$Gateway!/$Status' => 'gateway',
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
                $status
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
     * @param Payment $payment The payment that the service belongs to
     * @param string $status The string identifier of the ServiceResponse
     * @return void
     */
    protected function getServiceResponse($payment, $status)
    {
        $response = null;
        $intent = $this->getPaymentIntent($payment->Status);

        if ($intent) {
            $service = ServiceFactory::create()->getService($payment, $intent);

            switch ($status) {
                case "complete":
                    $response = $service->complete();
                    break;
                case "notify":
                    $response = $service->complete(array(), true);
                    break;
                case "cancel":
                    $response = $service->cancel();
                    break;
                default:
                    $response = null;
            }
        }

        return $response;
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
        $payment = $this->getPaymentFromRequest($this->request);

        if (!$payment) {
            return $this->httpError(404, _t('Payment.NotFound', 'Payment could not be found.'));
        }

        $response = $this->getServiceResponse(
            $payment,
            $this->request->param('Status')
        );

        if (!$response) {
            return $this->httpError(404, _t('Payment.InvalidUrl', 'Invalid payment url.'));
        }
        
        return $response->redirectOrRespond();
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

        // Does the selected gateway allow static routes
        if (!GatewayInfo::getConfigSetting($gateway, 'use_static_route')) {
            return $this->httpError(404, _t('Payment.InvalidUrl', 'Invalid payment url.'));
        }

        $payment = $this->getPaymentFromRequest($this->request, $gateway);

        if (!$payment) {
            return $this->httpError(404, _t('Payment.NotFound', 'Payment could not be found.'));
        }

        $response = $this->getServiceResponse(
            $payment,
            $this->request->param('Status')
        );

        if (!$response) {
            return $this->httpError(404, _t('Payment.InvalidUrl', 'Invalid payment url.'));
        }
        
        return $response->redirectOrRespond();
    }

    /**
     * Get the the payment according to the identifer given in the url
     * 
     * @param string $ident The identifier of the payment
     * @return \Payment the payment
     */
    private function getPaymentFromRequest(\SS_HTTPRequest $request, $gateway = null)
    {
        $identifier = $request->param("Identifier");
        $results = $this->extend("updatePaymentFromRequest", $request, $gateway);

        // Look to see if our extension return a payment,
        // if so, return it.
        foreach ($results as $result) {
            if ($result instanceof \Payment) {
                return $result;
            }
        }

        return \Payment::get()
                ->filter('Identifier', $identifier)
                ->filter('Identifier:not', "")
                ->first();
    }
}
