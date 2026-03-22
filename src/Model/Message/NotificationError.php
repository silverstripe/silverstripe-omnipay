<?php

namespace SilverStripe\Omnipay\Model\Message;

class NotificationError extends GatewayErrorMessage
{
    private static string $table_name = 'Omnipay_NotificationError';
}
