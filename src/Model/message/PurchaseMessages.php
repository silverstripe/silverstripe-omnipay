<?php

namespace SilverStripe\Omnipay\Model\Messaging;

class PurchaseRequest extends GatewayRequestMessage
{
}

class AwaitingPurchaseResponse extends GatewayResponseMessage
{
}

class PurchasedResponse extends GatewayResponseMessage
{
}

class CompletePurchaseRequest extends GatewayRequestMessage
{
}

class PurchaseError extends GatewayErrorMessage
{
}

class PurchaseRedirectResponse extends GatewayRedirectResponseMessage
{
}

class CompletePurchaseError extends GatewayErrorMessage
{
}
