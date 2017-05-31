<?php

use SilverStripe\Omnipay\Service\ServiceResponse;

/**
 * Add specific response overwrites for WorldPay gateway responses.
 * This allows us to provide a templated response to a WorldPay
 * payment notification and auto redirect to the current site after
 * payment.
 * 
 * If you need to accept payments via WorldPay and want to use the
 * custom payment response page, then you need to enable this extension
 * in your config.yml:
 * 
 * SilverStripe\Omnipay\Service\PaymentService:
 *  extensions:
 *    - WorldPayResponseExtension
 * 
 * If you would like to overwrite the default template (for example
 * to use your own styling, WPDISPLAY tags, etc) then add a
 * "WorldPayCallback.ss" file to the tempaltes directory in your
 * theme.
 * 
 * @package silverstripe-omnipay
 * @subpackage extensions
 * @author Bummzack
 * @author Mo <morven@ilateral.co.uk>
 */
class WorldPayResponseExtension extends Extension
{
  public function updateServiceResponse($response)
  {
    $payment = $response->getPayment();

    // We only want to respond to the notification if we are using WorldPay
    if ($payment->Gateway !== 'WorldPay') {
        return;
    }

    // Ignore payments that aren't in the PendingPurchase state
    if ($payment->Status !== 'PendingPurchase') {
        return;
    }

    $omnipayResponse = $response->getOmnipayResponse();

    if ($omnipayResponse !== null && !$response->isError()) {

        if ($omnipayResponse->isSuccessful()) {
            $return_url = Director::absoluteURL($payment->SuccessUrl, true);
        } else {
            $return_url = Director::absoluteURL($payment->FailureUrl, true);
        }

        $viewer = new SSViewer("WorldPayCallback");
        $html = $viewer->process(ArrayData::create(array(
            "ReturnURL" => $return_url
        )));

        $response->setHttpResponse(new SS_HTTPResponse($html, 200));
    }
  }
}
