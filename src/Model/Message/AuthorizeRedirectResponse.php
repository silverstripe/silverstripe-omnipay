<?php

namespace SilverStripe\Omnipay\Model\Message;

class AuthorizeRedirectResponse extends GatewayRedirectResponseMessage
{
    private static string $table_name = 'Omnipay_AuthorizeRedirectResponse';
}
