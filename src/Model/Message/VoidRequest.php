<?php

namespace SilverStripe\Omnipay\Model\Message;

class VoidRequest extends GatewayRequestMessage
{
    private static string $table_name = 'Omnipay_VoidRequest';
}
