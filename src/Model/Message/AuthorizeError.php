<?php

namespace SilverStripe\Omnipay\Model\Message;

class AuthorizeError extends GatewayErrorMessage
{
    private static $table_name = 'Omnipay_AuthorizeError';
}
