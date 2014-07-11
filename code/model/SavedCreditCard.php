<?php

/**
 * Stores information about a saved credit card. Only
 * used for gateways that support createCard.
 *
 * @property string CardReference - returned from gateway
 * @property string LastFourDigits - truncated credit card number
 * @property string Name - optional name of the card for user's use
 * @property int UserID
 * @method Member User()
 * @method HasManyList Payments()
 *
 * @package omnipay
 */
class SavedCreditCard extends DataObject {

    private static $db = array(
        'CardReference' => 'Varchar(255)',
        'LastFourDigits' => 'Varchar(10)',
        'Name' => 'Varchar(255)', // optional
    );

    private static $has_one = array(
        'User' => 'Member', // optional
    );

    private static $has_many = array(
        'Payments' => 'Payment',
    );

}