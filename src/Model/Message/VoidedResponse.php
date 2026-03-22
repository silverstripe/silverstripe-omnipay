<?php

namespace SilverStripe\Omnipay\Model\Message;

class VoidedResponse extends GatewayResponseMessage
{
    private static string $table_name = 'Omnipay_VoidedResponse';
}
