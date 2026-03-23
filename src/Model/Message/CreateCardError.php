<?php

namespace SilverStripe\Omnipay\Model\Message;

class CreateCardError extends GatewayErrorMessage
{
    private static string $table_name = 'Omnipay_CreateCardError';
}
