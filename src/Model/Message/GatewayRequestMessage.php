<?php

namespace SilverStripe\Omnipay\Model\Message;

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
    private static $db = [
        'SuccessURL' => 'Text',
        'FailureURL' => 'Text'
    ];

    private static $table_name = 'Omnipay_GatewayRequestMessage';
}
