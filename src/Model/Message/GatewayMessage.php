<?php

namespace SilverStripe\Omnipay\Model\Message;

/**
 * Class GatewayMessage
 * @package SilverStripe\Omnipay\Model\Message
 * @property string $Gateway
 * @property string $Reference
 * @property string $Code
 */
class GatewayMessage extends PaymentMessage
{
    private static array $db = [
        'Gateway' => 'Varchar',
        'Reference' => 'Varchar(255)',
        'Code' => 'Varchar'
    ];

    private static array $summary_fields = [
        'i18n_singular_name' => 'Type',
        'Message' => 'Message',
        'User.Name' => 'User',
        'Gateway' => 'Gateway',
        'Reference' => 'Reference',
        'Code' => 'Code'
    ];

    private static string $table_name = 'Omnipay_GatewayMessage';
}
