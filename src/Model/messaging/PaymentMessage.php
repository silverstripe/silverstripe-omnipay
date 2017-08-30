<?php

namespace SilverStripe\Omnipay\Model\Messaging;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;

/**
 * Base class for logging messages, transactions etc associated with a payment.
 *
 */
class PaymentMessage extends DataObject
{
    private static $db = array(
        "Message" => "Varchar(255)",
        "ClientIp" => "Varchar(39)"
    );

    private static $has_one = array(
        "Payment" => "Payment",
        "User" => "Member" //currently logged in user, if appliciable
    );

    private static $summary_fields = array(
        'i18n_singular_name' => "Type",
        'Message' => "Message",
        'User.Name' => "User"
    );

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
