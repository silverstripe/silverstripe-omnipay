<?php

namespace SilverStripe\Omnipay\Model\Message;

class CaptureError extends GatewayErrorMessage
{
    private static $table_name = 'Omnipay_CaptureError';
}
