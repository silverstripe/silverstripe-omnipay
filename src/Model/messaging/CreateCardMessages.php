<?php

namespace SilverStripe\Omnipay\Model\Messaging;

class CreateCardRequest extends GatewayRequestMessage
{
}

class AwaitingCreateCardResponse extends GatewayResponseMessage
{
}

class CreateCardResponse extends GatewayResponseMessage
{
}

class CreateCardRedirectResponse extends GatewayRedirectResponseMessage
{
}

class CreateCardError extends GatewayErrorMessage
{
}

class CompleteCreateCardRequest extends GatewayRequestMessage
{
}

class CompleteCreateCardError extends GatewayErrorMessage
{
}
