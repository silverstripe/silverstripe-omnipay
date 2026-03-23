<?php

namespace SilverStripe\Omnipay\Model\Message;

class AuthorizeError extends GatewayErrorMessage
{
    private static string $table_name = 'Omnipay_AuthorizeError';
}
