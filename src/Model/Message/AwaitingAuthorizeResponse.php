<?php

namespace SilverStripe\Omnipay\Model\Message;

class AwaitingAuthorizeResponse extends GatewayResponseMessage
{
    private static string $table_name = 'Omnipay_AwaitingAuthorizeResponse';
}
