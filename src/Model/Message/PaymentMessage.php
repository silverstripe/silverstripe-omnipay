<?php

namespace SilverStripe\Omnipay\Model\Message;

use SilverStripe\Omnipay\Migration\PaymentMessageMigrator;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBVarchar;
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

    /**
     * Upgrade messages written by omnipay 5.x or older on every `dev/build`. See {@link PaymentMessageMigrator}.
     * The check is cheap once there is nothing left to migrate, but you may disable it after upgrading.
     */
    private static bool $migrate_legacy_messages_on_build = true;

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

    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        if (!static::config()->get('migrate_legacy_messages_on_build')) {
            return;
        }

        foreach (PaymentMessageMigrator::create()->migrate() as $step => $count) {
            DB::alteration_message("Payment messages: {$step} ({$count})", 'changed');
        }
    }

    /**
     * Values often come from remote gateways; truncate them to the column size, so an over-long value can't
     * make the write fail (and leave the payment stuck mid-flow).
     */
    public function setField(string $fieldName, mixed $value): static
    {
        if (is_string($value) && isset(static::config()->get('db')[$fieldName])) {
            $dbField = $this->dbObject($fieldName);
            if ($dbField instanceof DBVarchar && mb_strlen($value) > $dbField->getSize()) {
                $value = mb_substr($value, 0, $dbField->getSize());
            }
        }

        return parent::setField($fieldName, $value);
    }

    /**
     * Payment messages are an audit log, so they can't be created, edited or deleted via the CMS by default.
     * @param Member|null $member
     * @param array<string, mixed> $context
     */
    public function canCreate($member = null, $context = [])
    {
        return $this->extendedCan(__FUNCTION__, $member, $context) ?? false;
    }

    /**
     * @param Member|null $member
     */
    public function canEdit($member = null)
    {
        return $this->extendedCan(__FUNCTION__, $member) ?? false;
    }

    /**
     * @param Member|null $member
     */
    public function canDelete($member = null)
    {
        return $this->extendedCan(__FUNCTION__, $member) ?? false;
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
     * @deprecated 6.1.0 Always returns {@link PaymentMessage}. Use PaymentMessage::create() instead.
     * @return class-string<self>
     */
    public static function classForMessageType(string $type): string
    {
        return self::class;
    }
}
