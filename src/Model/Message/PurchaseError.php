<?php

namespace SilverStripe\Omnipay\Model\Message;

class PurchaseError extends GatewayErrorMessage
{
    private static string $table_name = 'Omnipay_PurchaseError';
}
