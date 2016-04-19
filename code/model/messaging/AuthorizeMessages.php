<?php

class AuthorizeRequest extends GatewayRequestMessage
{
}

class AwaitingAuthorizeResponse extends GatewayResponseMessage
{
}

class AuthorizedResponse extends GatewayResponseMessage
{
}

class AuthorizeRedirectResponse extends GatewayRedirectResponseMessage
{
}

class AuthorizeError extends GatewayErrorMessage
{
}

class CompleteAuthorizeRequest extends GatewayRequestMessage
{
}

//AuthorizedResponse
class CompleteAuthorizeError extends GatewayErrorMessage
{
}
