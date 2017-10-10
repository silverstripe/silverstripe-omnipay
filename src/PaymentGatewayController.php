<?php

namespace SilverStripe\Omnipay;

use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;

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
    private static $allowed_actions = array(
        'endpoint'
    );

    /**
     * Generate an absolute url for gateways to return to, or send requests to.
     * @param  string $action the intended action of the gateway
     * @param  string $identifier the unique payment id
     * @return string the resulting redirect url
     */
    public static function getEndpointUrl($action, $identifier)
    {
        return Director::absoluteURL(
            Controller::join_links('paymentendpoint', $identifier, $action)
        );
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
        $payment = $this->getPayment();

        if (!$payment) {
            $this->httpError(404, _t('SilverStripe\\Omnipay\\Model\\Payment.NotFound', 'Payment could not be found.'));
        }

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
            default:
                $this->httpError(403, _t('SilverStripe\\Omnipay\\Model\\Payment.InvalidStatus', 'Invalid/unhandled payment status'));
        }

        $service = ServiceFactory::create()->getService($payment, $intent);

        //do the payment update
        switch ($this->request->param('Status')) {
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
                $this->httpError(404, _t('SilverStripe\\Omnipay\\Model\\Payment.InvalidUrl', 'Invalid payment url.'));
        }

        return $response;
    }

    /**
     * Get the the payment according to the identifer given in the url
     * @return Payment the payment
     */
    private function getPayment()
    {
        return Payment::get()
                ->filter('Identifier', $this->request->param('Identifier'))
                ->filter('Identifier:not', "")
                ->first();
    }
}
