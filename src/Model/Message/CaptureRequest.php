<?php

namespace SilverStripe\Omnipay\Model\Message;

class CaptureRequest extends GatewayRequestMessage
{
    private static string $table_name = 'Omnipay_CaptureRequest';
}
