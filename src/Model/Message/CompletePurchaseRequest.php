<?php

namespace SilverStripe\Omnipay\Model\Message;

class CompletePurchaseRequest extends GatewayRequestMessage
{
    private static string $table_name = 'Omnipay_CompletePurchaseRequest';
}
