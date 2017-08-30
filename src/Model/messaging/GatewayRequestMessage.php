<?php

namespace SilverStripe\Omnipay\Model\Messaging;

/**
 * Stores parameters for an offsite gateway request.
 *
 * Any responses from this gateway processing are likely redirected to
 * {@link PaymentGatewayController}. In there, the $SuccessURL and $FailureURL
 * properties can be used to further redirect the user based on the processed
 * payment status.
 */
class GatewayRequestMessage extends GatewayMessage
{
    private static $table_name = 'Omnipay_GatewayRequestMessage';

    private static $db = array(
        'SuccessURL' => 'Text',
        'FailureURL' => 'Text'
    );
}
