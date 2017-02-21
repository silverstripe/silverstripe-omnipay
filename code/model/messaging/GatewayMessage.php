<?php

class GatewayMessage extends PaymentMessage
{

    private static $db = array(
        "Gateway" => "Varchar",
        "Reference" => "Text", //remote id
        "Code" => "Varchar"
    );

    private static $summary_fields = array(
        'Type',
        'Reference',
        'Message',
        'Code'
    );
}
