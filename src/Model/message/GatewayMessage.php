<?php

namespace SilverStripe\Omnipay\Model\Message;

class GatewayMessage extends PaymentMessage
{
    private static $db = [
        "Gateway" => "Varchar",
        "Reference" => "Varchar(255)",
        "Code" => "Varchar"
    ];

    private static $summary_fields = [
        'Type',
        'Reference',
        'Message',
        'Code'
    ];

    private static $table_name = 'Omnipay_GatewayMessage';
}
