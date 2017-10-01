<?php

namespace SilverStripe\Omnipay\Model\Messaging;

class AuthorizeRequest extends GatewayRequestMessage
{
    private static $table_name = 'Omnipay_AuthorizeRequest';
}

class AwaitingAuthorizeResponse extends GatewayResponseMessage
{
    private static $table_name = 'Omnipay_AwaitingAuthorizeResponse';
}

class AuthorizedResponse extends GatewayResponseMessage
{
    private static $table_name = 'Omnipay_AuthorizedResponse';
}

class AuthorizeRedirectResponse extends GatewayRedirectResponseMessage
{
    private static $table_name = 'Omnipay_AuthorizeRedirectResponse';
}

class AuthorizeError extends GatewayErrorMessage
{
    private static $table_name = 'Omnipay_AuthorizeError';
}

class CompleteAuthorizeRequest extends GatewayRequestMessage
{
    private static $table_name = 'Omnipay_CompleteAuthorizeRequest';
}

//AuthorizedResponse
class CompleteAuthorizeError extends GatewayErrorMessage
{
    private static $table_name = 'Omnipay_CompleteAuthorizeError';
}
