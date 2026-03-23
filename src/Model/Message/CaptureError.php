<?php

namespace SilverStripe\Omnipay\Model\Message;

class CaptureError extends GatewayErrorMessage
{
    private static string $table_name = 'Omnipay_CaptureError';
}
