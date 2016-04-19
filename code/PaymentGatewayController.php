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
        return \Director::absoluteURL(
            \Controller::join_links('paymentendpoint', $identifier, $action)
        );
    }

	/**
	 * Generate an absolute url for gateways to return to, or send requests to.
	 * @param  string             $action      the intended action of the gateway
	 * @param  string             $returnurl   the application url to re-redirect to
	 * @return string                          the resulting redirect url
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use getEndpointUrl
	 */
	public static function get_endpoint_url($action, $identifier) {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0, use getEndpointUrl');
		return self::getEndpointUrl($action, $identifier);
	}

	/**
	 * The main action for handling all requests.
	 * It will redirect back to the application in all cases,
	 * but will not update the Payment/Transaction models if they are not found,
	 * or allowed to be updated.
	 */
	public function index() {
        $response = null;
		$payment = $this->getPayment();

		if (!$payment) {
			$this->httpError(404, _t('Payment.NOTFOUND', 'Payment could not be found.'));
		}

        $intent = null;
        switch ($payment->Status){
            case 'PendingAuthorization':
                $intent = ServiceFactory::INTENT_AUTHORIZE;
                break;
            case 'PendingCapture':
                $intent = ServiceFactory::INTENT_CAPTURE;
                break;
            case 'PendingPurchase':
                $intent = ServiceFactory::INTENT_PURCHASE;
                break;
            case 'PendingRefund':
                $intent = ServiceFactory::INTENT_REFUND;
                break;
            case 'PendingVoid':
                $intent = ServiceFactory::INTENT_VOID;
                break;
            default:
                $this->httpError(403, _t('Payment.InvalidStatus', 'Invalid/unhandled payment status'));
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
				$this->httpError(404, _t('Payment.INVALIDURL', 'Invalid payment url.'));
		}

		return $response;
	}

	/**
	 * Get the the payment according to the identifer given in the url
	 * @return \Payment the payment
	 */
	private function getPayment() {
		return \Payment::get()
				->filter('Identifier', $this->request->param('Identifier'))
				->filter('Identifier:not', "")
				->first();
	}
}
