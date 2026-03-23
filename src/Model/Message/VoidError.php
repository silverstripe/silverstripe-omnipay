<?php

namespace SilverStripe\Omnipay\Model\Message;

class VoidError extends GatewayErrorMessage
{
    private static string $table_name = 'Omnipay_VoidError';
}
