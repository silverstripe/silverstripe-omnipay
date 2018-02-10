<?php

namespace SilverStripe\Omnipay\Model;

use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Helper\PaymentMath;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\ORM\Filters\PartialMatchFilter;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\RandomGenerator;
use SilverStripe\Security\Permission;
use SilverStripe\Omnipay\Model\Message\PaymentMessage;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;

/**
 * Payment DataObject
 *
 * This class is used for storing a payment amount, and it's status of being
 * paid or not, and the gateway used to make payment.
 *
 * @property string $Gateway
 * @property DBMoney $Money
 * @property string $Status
 * @property string $Identifier
 * @property string $TransactionReference
 * @property string $SuccessUrl
 * @property string $FailureUrl
 * @property int $InitialPaymentID
 * @method Payment InitialPayment()
 * @method PaymentMessage[]|HasManyList Messages()
 */
final class Payment extends DataObject implements PermissionProvider
{
    private static $db = [
        'Gateway' => 'Varchar(128)', // this is the omnipay 'short name'
        'Money' => 'Money',
        'Status' => "Enum('Created,PendingAuthorization,Authorized,PendingCreateCard,CardCreated,PendingPurchase,PendingCapture,Captured,PendingRefund,Refunded,PendingVoid,Void','Created')",
        'Identifier' => 'Varchar(64)', // local unique identifier for this payment
        'TransactionReference' => 'Varchar(255)', // How this payment is being referenced by the payment provider
        'SuccessUrl' => 'Text',
        'FailureUrl' => 'Text'
    ];

    private static $has_one = [
        'InitialPayment' => Payment::class // partial payments will reference the initial payments with this relation
    ];

    private static $has_many = [
        'Messages' => PaymentMessage::class
    ];

    private static $defaults = [
        'Status' => 'Created'
    ];

    private static $casting = [
        'Amount' => 'Decimal'
    ];

    private static $summary_fields = [
        'Money' => 'Money',
        'GatewayTitle' => 'Gateway',
        'PaymentStatus' => 'Status',
        'Created.Nice' => 'Created'
    ];

    private static $indexes = [
        'Identifier' => true,
    ];

    private static $table_name = 'Omnipay_Payment';

    private static $default_sort = '"Created" DESC, "ID" DESC';

    /**
     * The allowed payment gateways
     * @config
     *
     * @var array
     */
    private static $allowed_gateways = [];

    public function getCMSFields()
    {
        $fields = new FieldList(
            TextField::create('MoneyValue', $this->fieldLabel('Money'), $this->dbObject('Money')->Nice()),
            TextField::create('GatewayTitle', $this->fieldLabel('Gateway'), 'Gateway')
        );

        $fields = $fields->makeReadonly();

        $fields->push(
            GridField::create(
                'Messages',
                $this->fieldLabel('Messages'),
                $this->Messages(),
                GridFieldConfig_RecordViewer::create()
            )
        );

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * Change search context to use a dropdown for list of gateways.
     */
    public function getDefaultSearchContext()
    {
        $context = parent::getDefaultSearchContext();
        $fields = $context->getSearchFields();

        $fields->removeByName('Gateway');
        $fields->removeByName('Created');

        $fields->insertAfter('Money', DropdownField::create(
            'Gateway',
            $this->fieldLabel('Gateway'),
            GatewayInfo::getSupportedGateways()
        )->setHasEmptyDefault(true));

        // create a localized status dropdown for the search-context
        $fields->insertAfter('Gateway', DropdownField::create(
            'Status',
            $this->fieldLabel('Status'),
            $this->getStatusValues()
        )->setHasEmptyDefault(true));

        $context->addFilter(new PartialMatchFilter('Gateway'));

        return $context;
    }

    /**
     * Set gateway, amount, and currency in one function.
     * @param string $gateway Omnipay gateway short name
     * @param float $amount monetary amount
     * @param string $currency the currency to set
     * @return $this object for chaining
     */
    public function init($gateway, $amount, $currency)
    {
        $this->setGateway($gateway);
        $this->setAmount($amount);
        $this->setCurrency($currency);
        return $this;
    }

    /**
     * Set the url to redirect to after payment is made/attempted.
     *
     * This function also populates the FailureUrl, if it is empty.
     *
     * @param string $url
     *
     * @return $this object for chaining
     */
    public function setSuccessUrl($url)
    {
        $this->setField('SuccessUrl', $url);
        if (!$this->FailureUrl) {
            $this->setField('FailureUrl', $url);
        }

        return $this;
    }

    /**
     * Set the url to redirect to after payment is cancelled.
     *
     * @return $this this object for chaining
     */
    public function setFailureUrl($url)
    {
        $this->setField('FailureUrl', $url);

        return $this;
    }

    /**
     * Locale aware title for a payment.
     * Consists of Gateway-Name, Money and Currency, Created date.
     *
     * Uses a translatable string as template for the output.
     * @return string
     */
    public function getTitle()
    {
        return strftime(_t(
            'SilverStripe\Omnipay\Model\Payment.TitleTemplate',
            '{Gateway} {Money} %d/%m/%Y',
            'A template for the payment title',
            str_replace('%', '%%', array(
                'Gateway' => $this->getGatewayTitle(),
                'Money' => $this->dbObject('Money')->Nice()
            ))
        ), strtotime($this->Created));
    }

    /**
     * Set the payment gateway
     * @param string $gateway the omnipay gateway short name.
     * @return Payment this object for chaining
     */
    public function setGateway($gateway)
    {
        if ($this->Status == 'Created') {
            $this->setField('Gateway', $gateway);
        }
        return $this;
    }

    /**
     * The payment gateway title (localized, if available)
     * @return string
     */
    public function getGatewayTitle()
    {
        return GatewayInfo::niceTitle($this->Gateway);
    }

    /**
     * Get the payment status. This will return a localized value if available.
     * @return string the payment status
     */
    public function getPaymentStatus()
    {
        return _t('SilverStripe\Omnipay\Model\Payment.STATUS_' . strtoupper($this->Status), $this->Status);
    }

    /**
     * Get the payment amount
     * @return string amount of this payment
     */
    public function getAmount()
    {
        return $this->MoneyAmount;
    }

    /**
     * Set the payment amount, but only when the status is 'Created'.
     * @param float $amount value to set the payment to
     * @return  Payment this object for chaining
     */
    public function setAmount($amount)
    {
        if ($amount instanceof DBMoney) {
            $this->setField('Money', $amount);
        } elseif ($this->Status == 'Created' && is_numeric($amount)) {
            $this->MoneyAmount = $amount;
        }
        return $this;
    }

    /**
     * Get just the currency of this payment's money component
     * @return string the currency of this payment
     */
    public function getCurrency()
    {
        return $this->MoneyCurrency;
    }

    /**
     * Set the payment currency, but only when the status is 'Created'.
     * @param string $currency the currency to set
     */
    public function setCurrency($currency)
    {
        if ($this->Status == 'Created') {
            $this->MoneyCurrency = $currency;
        }

        return $this;
    }

    /**
     * This payment requires no more processing.
     * @return boolean completion
     */
    public function isComplete()
    {
        return
            $this->Status == 'CardCreated' ||
            $this->Status == 'Captured' ||
            $this->Status == 'Refunded' ||
            $this->Status == 'Void';
    }

    /**
     * Get a message of a given type
     * @param $type
     * @return mixed
     */
    public function getLatestMessageOfType($type)
    {
        if (!$this->isInDB()) {
            return null;
        }
        return $this->Messages()
            ->filter('ClassName', $type)
            ->first();
    }

    /**
     * Get partial payments that have this payment as initial payment.
     * The list will be sorted from newest to oldest
     * @return DataList|null
     */
    public function getPartialPayments()
    {
        if (!$this->isInDB()) {
            return null;
        }

        return Payment::get()
            ->filter('InitialPaymentID', $this->ID)
            ->sort(array('Created' => 'DESC', 'ID' => 'DESC'));
    }

    /**
     * Whether or not this payment is a partial payment of another payment
     * @return bool
     */
    public function isPartial()
    {
        return !empty($this->InitialPaymentID);
    }

    /**
     * Check the payment is captured.
     * @return boolean completion
     */
    public function isCaptured()
    {
        return $this->Status == 'Captured';
    }

    public function forTemplate()
    {
        return $this->dbObject('Money');
    }

    /**
     * Calculate the max amount that can be captured for this payment.
     * If the Status of the payment isn't 'Authorized', this will return 0
     * @return int|string the max amount that can be captured for this payment.
     */
    public function getMaxCaptureAmount()
    {
        if ($this->Status !== 'Authorized') {
            return 0;
        }

        $percent = GatewayInfo::maxExcessCapturePercent($this->Gateway);
        $fixedAmount = GatewayInfo::maxExcessCaptureAmount($this->Gateway, $this->getCurrency());

        // -1 will only be returned if there's a fixed amount, but no percentage.
        // We can safely return the fixed amount here
        if ($percent === -1) {
            return PaymentMath::add($this->MoneyAmount, $fixedAmount);
        }

        // calculate what amount the percentage will result in
        $percentAmount = PaymentMath::multiply(PaymentMath::multiply($percent, '0.01'), $this->MoneyAmount);

        // if there's no fixed amount and only the percentage is set, we can return the percentage amount right away.
        if ($fixedAmount === -1) {
            return PaymentMath::add($this->MoneyAmount, $percentAmount);
        }

        // If the amount from the percentage is smaller, use the percentage
        if (PaymentMath::compare($fixedAmount, $percentAmount) > 0) {
            return PaymentMath::add($this->MoneyAmount, $percentAmount);
        }

        // otherwise use the fixed amount
        return PaymentMath::add($this->MoneyAmount, $fixedAmount);
    }

    /**
     * Whether or not this payment can be captured
     * @param Member|int $member the member/memberID to check permissions on
     * @param boolean $partial check if payment can be partially captured. Defaults to false
     * @return bool
     */
    public function canCapture($member = null, $partial = false)
    {
        // premise
        if (!(
            $this->Status === 'Authorized' &&
            ($partial ? GatewayInfo::allowPartialCapture($this->Gateway) : GatewayInfo::allowCapture($this->Gateway))
        )) {
            return false;
        }

        if ($this->isInDB()) {
            // Check if there are partial captures and deny further captures if multiple captures aren't enabled
            $hasPartials = $this->getPartialPayments()
                    ->filter('Status', array('Captured', 'PendingCapture'))
                    ->count() > 0;
            if ($hasPartials && GatewayInfo::captureMode($this->Gateway) !== GatewayInfo::MULTIPLE) {
                return false;
            }
        }

        $extended = $this->extendedCan('canCapture', $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('CAPTURE_PAYMENTS', 'any', $member);
    }

    /**
     * Whether or not this payment can be voided
     * @param Member|int $member the member/memberID to check permissions on
     * @return bool
     */
    public function canVoid($member = null)
    {
        if (!($this->Status == 'Authorized' && GatewayInfo::allowVoid($this->Gateway))) {
            return false;
        }

        $extended = $this->extendedCan('canVoid', $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('VOID_PAYMENTS', 'any', $member);
    }

    /**
     * Whether or not this payment can be refunded
     * @param Member|int $member the member/memberID to check permissions on
     * @param boolean $partial check if payment can be partially refunded. Defaults to false
     * @return bool
     */
    public function canRefund($member = null, $partial = false)
    {
        if (!(
            $this->Status == 'Captured' &&
            ($partial ? GatewayInfo::allowPartialRefund($this->Gateway) : GatewayInfo::allowRefund($this->Gateway))
        )) {
            return false;
        }

        if ($this->isInDB()) {
            // Check if there are partial refunds and deny further refunds if multiple refunds aren't enabled
            $hasPartials = $this->getPartialPayments()
                    ->filter('Status', array('Refunded', 'PendingRefund'))
                    ->count() > 0;
            if ($hasPartials && GatewayInfo::refundMode($this->Gateway) !== GatewayInfo::MULTIPLE) {
                return false;
            }
        }

        $extended = $this->extendedCan('canRefund', $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('REFUND_PAYMENTS', 'any', $member);
    }

    /**
     * Provide payment related permissions. The permissions are:
     * * `REFUND_PAYMENTS` can refund payments
     * * `CAPTURE_PAYMENTS` can capture payments
     * * `VOID_PAYMENTS` can void payments
     * @inheritdoc
     * @return array
     */
    public function providePermissions()
    {
        return array(
            'REFUND_PAYMENTS' => array(
                'name' => _t('SilverStripe\Omnipay\Model\Payment.PERMISSION_REFUND_PAYMENTS', 'Refund payments'),
                'help' => _t(
                    'SilverStripe\Omnipay\Model\Payment.PERMISSION_REFUND_PAYMENTS_HELP',
                    'Allow refunding of captured payments'
                ),
                'category' => _t('SilverStripe\Omnipay\Model\Payment.PAYMENT_PERMISSIONS', 'Payment actions'),
                'sort' => 200
            ),
            'CAPTURE_PAYMENTS' => array(
                'name' => _t('SilverStripe\Omnipay\Model\Payment.PERMISSION_CAPTURE_PAYMENTS', 'Capture payments'),
                'help' => _t(
                    'SilverStripe\Omnipay\Model\Payment.PERMISSION_CAPTURE_PAYMENTS_HELP',
                    'Allow capturing of authorized payments'
                ),
                'category' => _t('SilverStripe\Omnipay\Model\Payment.PAYMENT_PERMISSIONS', 'Payment actions'),
                'sort' => 200
            ),
            'VOID_PAYMENTS' => array(
                'name' => _t('SilverStripe\Omnipay\Model\Payment.PERMISSION_VOID_PAYMENTS', 'Void payments'),
                'help' => _t(
                    'SilverStripe\Omnipay\Model\Payment.PERMISSION_VOID_PAYMENTS_HELP',
                    'Allow voiding of authorized payments'
                ),
                'category' => _t('SilverStripe\Omnipay\Model\Payment.PAYMENT_PERMISSIONS', 'Payment actions'),
                'sort' => 200
            )
        );
    }

    /**
     * Only allow setting identifier, if one doesn't exist yet.
     * @param string $id identifier
     */
    public function setIdentifier($id)
    {
        if (!$this->Identifier) {
            $this->setField('Identifier', $id);
        }
    }

    public function provideI18nEntities()
    {
        $entities = parent::provideI18nEntities();

        // collect all the payment status values
        foreach ($this->dbObject('Status')->enumValues() as $value) {
            $key = strtoupper($value);
            $entities['SilverStripe\Omnipay\Model\Payment.STATUS_' . $key] = array(
                $value,
                "Translation of the payment status '$value'"
            );
        }

        return $entities;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->Identifier) {
            $this->Identifier = $this->generateUniquePaymentIdentifier();
        }
    }

    /**
     * Generate an internally unique string that identifies a payment,
     * and can be used in URLs.
     * @return string Identifier
     */
    protected function generateUniquePaymentIdentifier()
    {
        $generator = Injector::inst()->create(RandomGenerator::class);
        $id = null;
        do {
            $id = substr($generator->randomToken(), 0, 30);
        } while (!$id && self::get()->filter('Identifier', $id)->exists());

        return $id;
    }

    /**
     * Get an array of status enum value to translated string.
     * Can be used for dropdowns
     * @return array
     */
    protected function getStatusValues()
    {
        $values = array();
        foreach ($this->dbObject('Status')->enumValues() as $value) {
            $values[$value] = _t('SilverStripe\Omnipay\Model\Payment.STATUS_' . strtoupper($value), $value);
        }
        return $values;
    }
}
