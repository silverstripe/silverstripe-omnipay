<?php

namespace SilverStripe\Omnipay\Model\Messaging;

class VoidRequest extends GatewayRequestMessage
{
    private static $table_name = 'Omnipay_VoidRequest';
}

class VoidedResponse extends GatewayResponseMessage
{
    private static $table_name = 'Omnipay_VoidedResponse';
}

class VoidError extends GatewayErrorMessage
{
    private static $table_name = 'Omnipay_VoidError';
}
