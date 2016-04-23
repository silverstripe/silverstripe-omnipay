<?php

use SilverStripe\Omnipay\GatewayInfo;

/**
 * Payment DataObject
 *
 * This class is used for storing a payment amount, and it's status of being
 * paid or not, and the gateway used to make payment.
 *
 * @package payment
 */
final class Payment extends DataObject
{
    private static $db = array(
        // this is the omnipay 'short name'
        'Gateway' => 'Varchar(128)',
        //contains Amount and Currency
        'Money' => 'Money',
        // status
        'Status' => "Enum('Created,PendingAuthorization,Authorized,PendingPurchase,PendingCapture,Captured,PendingRefund,Refunded,PendingVoid,Void','Created')",
        // unique identifier for this payment
        'Identifier' => 'Varchar(64)',
        // How this payment is being referenced by the payment provider
        'TransactionReference' => 'Varchar(255)',
        // Success URL
        'SuccessUrl' => 'Text',
        // Failure URL
        'FailureUrl' => 'Text'
    );

    private static $has_many = array(
        'Messages' => 'PaymentMessage'
    );

    private static $defaults = array(
        'Status' => 'Created'
    );

    private static $casting = array(
        'Amount' => 'Decimal'
    );

    private static $summary_fields = array(
        'Money' => 'Money',
        'GatewayTitle' => 'Gateway',
        'PaymentStatus' => 'Status',
        'Created.Nice' => 'Created'
    );

    private static $indexes = array(
        'Identifier' => true,
    );

    private static $default_sort = '"Created" DESC, "ID" DESC';

    public function getCMSFields()
    {
        $fields = new FieldList(
            TextField::create('MoneyValue', _t('Payment.db_Money', 'Money'), $this->dbObject('Money')->Nice()),
            TextField::create('GatewayTitle', _t('Payment.db_Gateway', 'Gateway'))
        );
        $fields = $fields->makeReadonly();
        $fields->push(
            GridField::create(
                'Messages',
                _t('Payment.has_many_Messages', 'Messages'),
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
        $fields->insertAfter(DropdownField::create('Gateway', _t('Payment.db_Gateway', 'Gateway'),
            GatewayInfo::getSupportedGateways()
        )->setHasEmptyDefault(true), 'Money');

        // create a localized status dropdown for the search-context
        $fields->insertAfter(DropdownField::create('Status', _t('Payment.db_Status', 'Status'),
            $this->getStatusValues()
        )->setHasEmptyDefault(true), 'Gateway');

        // update "money" to localized title
        $fields->fieldByName('Money')->setTitle(_t('Payment.db_Money', 'Money'));

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
     * This function also populates the FailureUrl, if it is empty.
     * @param string $url
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
     * Set the url to redirect to after payment is cancelled
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
            'Payment.TitleTemplate',
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
        return _t('Payment.STATUS_' . strtoupper($this->Status), $this->Status);
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
     * @param float $amt value to set the payment to
     * @return  Payment this object for chaining
     */
    public function setAmount($amount)
    {
        if ($amount instanceof Money) {
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
     * Whether or not this payment can be captured
     * @return bool
     */
    public function canCapture()
    {
        return ($this->Status == 'Authorized' && GatewayInfo::allowCapture($this->Gateway));
    }

    /**
     * Whether or not this payment can be voided
     * @return bool
     */
    public function canVoid()
    {
        return ($this->Status == 'Authorized' && GatewayInfo::allowVoid($this->Gateway));
    }

    /**
     * Whether or not this payment can be refunded
     * @return bool
     */
    public function canRefund()
    {
        return ($this->Status == 'Captured' && GatewayInfo::allowRefund($this->Gateway));
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
    public function generateUniquePaymentIdentifier()
    {
        $generator = Injector::inst()->create('RandomGenerator');
        $id = null;
        do {
            $id = substr($generator->randomToken(), 0, 30);
        } while (!$id && self::get()->filter('Identifier', $id)->exists());

        return $id;
    }

    public function provideI18nEntities()
    {
        $entities = parent::provideI18nEntities();

        // collect all the payment status values
        foreach ($this->dbObject('Status')->enumValues() as $value) {
            $key = strtoupper($value);
            $entities["Payment.STATUS_$key"] = array(
                $value,
                "Translation of the payment status '$value'"
            );
        }

        return $entities;
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
            $values[$value] = _t('Payment.STATUS_' . strtoupper($value), $value);
        }
        return $values;
    }
}
