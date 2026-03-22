<?php

namespace SilverStripe\Omnipay\Model\Message;

class RefundedResponse extends GatewayResponseMessage
{
    private static string $table_name = 'Omnipay_RefundedResponse';
}
