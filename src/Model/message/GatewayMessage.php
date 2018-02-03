<?php

namespace SilverStripe\Omnipay\Model\Message;

class GatewayMessage extends PaymentMessage
{
    private static $db = [
        'Gateway' => 'Varchar',
        'Reference' => 'Varchar(255)',
        'Code' => 'Varchar'
    ];

    private static $summary_fields = [
        'i18n_singular_name' => 'Type',
        'Message' => 'Message',
        'User.Name' => 'User',
        'Gateway' => 'Gateway',
        'Reference' => 'Reference',
        'Code' => 'Code'
    ];

    private static $table_name = 'Omnipay_GatewayMessage';
}
