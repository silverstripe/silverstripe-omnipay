<?php

namespace SilverStripe\Omnipay\Model\Messaging;

class NotificationSuccessful extends GatewayResponseMessage
{
}

class NotificationPending extends GatewayResponseMessage
{
}

class NotificationError extends GatewayErrorMessage
{
}
