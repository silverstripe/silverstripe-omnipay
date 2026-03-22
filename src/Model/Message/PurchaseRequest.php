<?php

namespace SilverStripe\Omnipay\Model\Message;

class PurchaseRequest extends GatewayRequestMessage
{
    private static string $table_name = 'Omnipay_PurchaseRequest';
}
