<?php

namespace SilverStripe\Omnipay\Model\Message;

class RefundRequest extends GatewayRequestMessage
{
    private static string $table_name = 'Omnipay_RefundRequest';
}
