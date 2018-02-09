<?php

namespace SilverStripe\Omnipay;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceFactory;

/**
 * Payment Gateway Controller
 *
 * This controller handles redirects from gateway servers, and also behind-the-scenes
 * requests that gateway servers to notify our application of successful payment.
 *
 * @package payment
 */
class PaymentGatewayController extends Controller
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
     * @param  string $action the intended action of the gateway
     * @param  string $identifier the unique payment id
     * @return string the resulting redirect url
     */
    public static function getEndpointUrl($action, $identifier)
    {
        $url = Controller::join_links(
            'paymentendpoint',
            $identifier,
            $action
        );

        return Director::absoluteURL($url);
    }

    /**
     * Get the static endpoint url for a gateway.
     * Attention: This only returns a URL if the `use_static_route` config is set for the gateway
     * @param string $gateway the gateway name
     * @param string $action (optional) the action to use (complete, cancel, notify)
     * @return string the static gateway route
     */
    public static function getStaticEndpointUrl($gateway, $action = null)
    {
        if (!GatewayInfo::getConfigSetting($gateway, 'use_static_route')) {
            return '';
        }

        $url = Controller::join_links(
            'paymentendpoint',
            'gateway',
            $gateway,
            $action
        );

        return Director::absoluteURL($url);
    }

    /**
     * The main action for handling all requests.
     * It will redirect back to the application in all cases,
     * but will not update the Payment/Transaction models if they are not found,
     * or allowed to be updated.
     * @return HTTPResponse
     * @throws Exception\InvalidConfigurationException
     * @throws Exception\InvalidStateException
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    public function index()
    {
        $payment = $this->getPaymentFromRequest($this->request);

        return $this->createPaymentResponse($payment);
    }

    /**
     * Action used for handling static gateway requests (use this if your payment gateway doesn't handle
     * dynamic callback URLs)
     * @return HTTPResponse
     * @throws Exception\InvalidConfigurationException
     * @throws Exception\InvalidStateException
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    public function gateway()
    {
        $response = null;
        $gateway = $this->request->param('Gateway');

        // Does the selected gateway allow static routes
        if (!GatewayInfo::getConfigSetting($gateway, 'use_static_route')) {
            return $this->httpError(
                404,
                _t('SilverStripe\Omnipay\Model\Payment.InvalidUrl', 'Invalid payment url.')
            );
        }

        $payment = $this->getPaymentFromRequest($this->request, $gateway);

        return $this->createPaymentResponse($payment);
    }

    /**
     * Find the intent of the current payment
     *
     * @param Payment $payment the payment object
     * @return string|null
     */
    protected function getPaymentIntent($payment)
    {
        $intent = null;

        switch ($payment->Status) {
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
        }

        return $intent;
    }

    /**
     * Create the appropriate HTTP response for the given payment.
     *
     * @param Payment $payment the payment that should be processed
     * @return HTTPResponse
     * @throws Exception\InvalidConfigurationException
     * @throws Exception\InvalidStateException
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    protected function createPaymentResponse($payment)
    {
        if (!$payment) {
            return $this->httpError(
                404,
                _t('SilverStripe\Omnipay\Model\Payment.NotFound', 'Payment could not be found.')
            );
        }

        $intent = $this->getPaymentIntent($payment);
        if (!$intent) {
            return $this->httpError(
                403,
                _t('SilverStripe\Omnipay\Model\Payment.InvalidStatus', 'Invalid/unhandled payment status')
            );
        }

        $service = ServiceFactory::create()->getService($payment, $intent);
        $response = null;
        //do the payment update
        switch ($this->getPaymentActionFromRequest($this->request, $payment)) {
            case "complete":
                $serviceResponse = $service->complete();
                $response = $serviceResponse->redirectOrRespond();
                break;
            case "notify":
                $serviceResponse = $service->complete(array(), true);
                $response = $serviceResponse->redirectOrRespond();
                break;
            case "cancel":
                $serviceResponse = $service->cancel();
                $response = $serviceResponse->redirectOrRespond();
                break;
            default:
                $response = $this->httpError(
                    404,
                    _t('SilverStripe\Omnipay\Model\Payment.InvalidUrl', 'Invalid payment url.')
                );
        }

        return $response;
    }

    /**
     * Get the action/service that should be performed on the payment.
     * Can be "complete", "notify" or "cancel".
     *
     * Extensions can update the payment action via `updatePaymentActionFromRequest` hook.
     * This can be useful if you're not allowed to have different static endpoints for the different actions
     * (eg. if the action is being sent as a http parameter instead).
     *
     * @param HTTPRequest $request The current request
     * @param Payment $payment
     * @return string
     */
    protected function getPaymentActionFromRequest(HTTPRequest $request, $payment)
    {
        $action = $request->param('Status');
        $this->extend('updatePaymentActionFromRequest', $action, $payment, $request);
        return $action;
    }

    /**
     * Get the the payment according to the identifier given in the url
     *
     * @param HTTPRequest $request The current request
     * @param string $gateway the gateway name
     * @return Payment the payment
     */
    protected function getPaymentFromRequest(HTTPRequest $request, $gateway = null)
    {
        $identifier = $request->param('Identifier');
        $results = $this->extend('updatePaymentFromRequest', $request, $gateway);

        // Look to see if our extension return a payment,
        // if so, return it.
        foreach ($results as $result) {
            if ($result instanceof Payment) {
                return $result;
            }
        }

        return Payment::get()
                ->filter('Identifier', $identifier)
                ->filter('Identifier:not', '')
                ->first();
    }
}
