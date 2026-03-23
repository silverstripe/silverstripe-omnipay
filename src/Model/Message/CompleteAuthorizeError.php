<?php

namespace SilverStripe\Omnipay\Model\Message;

class CompleteAuthorizeError extends GatewayErrorMessage
{
    private static string $table_name = 'Omnipay_CompleteAuthorizeError';
}
