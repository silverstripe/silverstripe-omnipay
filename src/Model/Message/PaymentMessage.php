<?php

namespace SilverStripe\Omnipay\Model\Message;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\Security\Member;
use SilverStripe\Omnipay\Model\Payment;

/**
 * Logs gateway-related messages and metadata for a payment.
 *
 * The semantic kind of message is stored in {@link self::Type} (see message-type constants on each
 * {@link \SilverStripe\Omnipay\Service\PaymentService} subclass).
 *
 * @property string $Message
 * @property string $ClientIp
 * @property string $Gateway
 * @property string $Reference
 * @property string $Code
 * @property string $Type
 * @property string $SuccessURL
 * @property string $FailureURL
 * @property int $PaymentID
 * @property int $UserID
 * @method null|Payment Payment()
 * @method null|Member Member()
 */
class PaymentMessage extends DataObject
{
    private static array $db = [
        'Message' => 'Varchar(255)',
        'ClientIp' => 'Varchar(39)',
        'Gateway' => 'Varchar',
        'Reference' => 'Varchar(255)',
        'Code' => 'Varchar',
        'Type' => 'Varchar(128)',
        'SuccessURL' => 'Text',
        'FailureURL' => 'Text',
    ];

    private static array $has_one = [
        'Payment' => Payment::class,
        'User' => Member::class,
    ];

    private static array $summary_fields = [
        'Type' => 'Type',
        'Message' => 'Message',
        'User.Name' => 'User',
        'Gateway' => 'Gateway',
        'Reference' => 'Reference',
        'Code' => 'Code',
    ];

    private static array $indexes = [
        'Type' => true,
    ];

    private static string $table_name = 'Omnipay_PaymentMessage';

    public function getCMSFields()
    {
        return parent::getCMSFields()->makeReadOnly();
    }

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        if (!$this->UserID && !$this->isInDB()) {
            if ($member = Security::getCurrentUser()) {
                $this->UserID = $member->ID;
            }
        }
    }

    public function i18n_singular_name()
    {
        if ($this->Type) {
            return _t(__CLASS__ . '.TYPE_' . $this->Type, $this->Type);
        }
        return parent::i18n_singular_name();
    }

    public function getTitle()
    {
        return $this->i18n_singular_name();
    }

    /**
     * Whether this message type stores offsite request URLs ({@link self::SuccessURL} / {@link self::FailureURL}).
     */
    public static function isRequestMessageType(string $type): bool
    {
        return str_ends_with($type, 'Request');
    }

    /**
     * @return class-string<self>
     */
    public static function classForMessageType(string $type): string
    {
        return self::class;
    }
}
