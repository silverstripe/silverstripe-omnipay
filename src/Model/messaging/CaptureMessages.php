<?php

namespace SilverStripe\Omnipay\Model\Messaging;

class CaptureRequest extends GatewayRequestMessage
{
}

class CapturedResponse extends GatewayResponseMessage
{
}

class PartiallyCapturedResponse extends GatewayResponseMessage
{
}

class CaptureError extends GatewayErrorMessage
{
}
