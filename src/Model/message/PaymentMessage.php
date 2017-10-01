<?php

namespace SilverStripe\Omnipay\Model\Message;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\Security\Member;
use SilverStripe\Omnipay\Model\Payment;

/**
 * Base class for logging messages, transactions etc associated with a payment.
 *
 */
class PaymentMessage extends DataObject
{
    private static $db = [
        'Message' => 'Varchar(255)',
        'ClientIp' => 'Varchar(39)'
    ];

    private static $has_one = [
        'Payment' => Payment::class,
        'User' => Member::class
    ];

    private static $summary_fields = [
        'i18n_singular_name' => 'Type',
        'Message' => 'Message',
        'User.Name' => 'User'
    ];

    private static $table_name = 'Omnipay_PaymentMessage';

    public function getCMSFields()
    {
        return parent::getCMSFields()->makeReadOnly();
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->UserID && !$this->isInDB()) {
            if ($member = Security::getCurrentUser()) {
                $this->UserID = $member->ID;
            }
        }
    }

    public function getTitle()
    {
        return $this->i18n_singular_name();
    }
}
