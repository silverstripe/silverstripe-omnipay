<?php

namespace SilverStripe\Omnipay\Model\Message;

class CompletePurchaseError extends GatewayErrorMessage
{
    private static string $table_name = 'Omnipay_CompletePurchaseError';
}
