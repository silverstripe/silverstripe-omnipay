<?php

namespace SilverStripe\Omnipay\Model\Message;

class NotificationPending extends GatewayResponseMessage
{
    private static string $table_name = 'Omnipay_NotificationPending';
}
