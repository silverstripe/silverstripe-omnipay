<?php

namespace SilverStripe\Omnipay\Model\Message;

class AuthorizeRequest extends GatewayRequestMessage
{
    private static string $table_name = 'Omnipay_AuthorizeRequest';
}
