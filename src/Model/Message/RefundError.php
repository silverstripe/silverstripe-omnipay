<?php

namespace SilverStripe\Omnipay\Model\Message;

class RefundError extends GatewayErrorMessage
{
    private static string $table_name = 'Omnipay_RefundError';
}
