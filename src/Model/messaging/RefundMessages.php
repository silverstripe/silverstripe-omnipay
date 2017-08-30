<?php

namespace SilverStripe\Omnipay\Model\Messaging;

class RefundRequest extends GatewayRequestMessage
{
}

class RefundedResponse extends GatewayResponseMessage
{
}

class PartiallyRefundedResponse extends GatewayResponseMessage
{
}

class RefundError extends GatewayErrorMessage
{
}
