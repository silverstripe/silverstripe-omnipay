<?php

namespace SilverStripe\Omnipay;

use Omnipay\Common\CreditCard;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\EmailField;

/**
 * Helper for generating gateway fields, based on best practices.
 *
 */
class GatewayFieldsFactory
{
    use Configurable;

    protected $fieldgroups = [
        'Card',
        'Billing',
        'Shipping',
        'Company',
        'Email'
    ];

    protected $gateway;

    /**
     * @var boolean
     */
    protected $groupdatefields = true;

    /**
     * @var array
     */
    protected $renamemap = [];

    /**
     * @config
     *
     * @var array
     */
    private static $whitelist = [
        'type',
        'name',
        'number',
        'startMonth',
        'startYear',
        'expiryMonth',
        'expiryYear',
        'cvv',
        'issueNumber',
        'billingAddress1',
        'billingAddress2',
        'billingCity',
        'billingPostcode',
        'billingState',
        'billingCountry',
        'billingPhone',
        'shippingAddress1',
        'shippingAddress2',
        'shippingCity',
        'shippingPostcode',
        'shippingState',
        'shippingCountry',
        'shippingPhone',
        'email',
        'company'
    ];

    /**
     * GatewayFieldsFactory constructor.
     *
     * @param string|null $gateway the gateway to create fields for. @see setGateway
     * @param array $fieldgroups the field-groups to create
     */
    public function __construct($gateway = null, $fieldgroups = null)
    {
        $this->setGateway($gateway);
        $this->setFieldGroups($fieldgroups);
    }

    /**
     * The field groups to create.
     *
     * An array with field-groups to create. Valid entries are: `'Card', 'Billing', 'Shipping', 'Company', 'Email'`.
     *
     * @param array $groups the groups to create
     * @return $this
     */
    public function setFieldGroups($groups)
    {
        if (is_array($groups)) {
            $this->fieldgroups = $groups;
        }

        return $this;
    }

    /**
     * Set the gateway to create fields for.
     *
     * If a gateway is given, only the required fields of that gateway will be returned! If the gateway isn't set, all
     * fields will be returned.
     *
     * @param string|null $gateway the gateway to create fields for.
     * @return $this
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
        $this->buildRenameMap();
        return $this;
    }

    /**
     * Get all the fields from the defined Field-Groups (via constructor or @see setFieldGroups)
     *
     * @return FieldList
     */
    public function getFields()
    {
        $fields = FieldList::create();

        foreach ($this->fieldgroups as $group) {
            if (method_exists($this, 'get'.$group.'Fields')) {
                $fields->merge($this->{'get'.$group.'Fields'}());
            }
        }

        return $fields;
    }

    /**
     * Get Credit-Card fields
     * @return FieldList
     */
    public function getCardFields()
    {
        $months = array();
        //generate list of months
        for ($x = 1; $x <= 12; $x++) {
            // Fixes #145 - Thanks to @digitall-it
            $months[$x] = str_pad($x, 2, '0', STR_PAD_LEFT) . " - " . strftime('%B', mktime(0, 0, 0, $x));
        }
        $year = date('Y');
        $range = 5;
        $startrange = range(date('Y', strtotime("-$range years")), $year);
        $expiryrange = range($year, date('Y', strtotime("+$range years")));

        $fields = array(
            'type' => DropdownField::create(
                $this->getFieldName('type'),
                _t('PaymentForm.Type', 'Type'),
                $this->getCardTypes()
            ),
            'name' => TextField::create(
                $this->getFieldName('name'),
                _t('PaymentForm.Name', 'Name on Card')
            ),
            'number' => TextField::create(
                $this->getFieldName('number'),
                _t('PaymentForm.Number', 'Card Number')
            )->setDescription(_t('PaymentForm.NumberDescription', 'no dashes or spaces')),
            'startMonth' => DropdownField::create(
                $this->getFieldName('startMonth'),
                _t('PaymentForm.StartMonth', 'Month'),
                $months
            )->setHasEmptyDefault(true)->setEmptyString(_t('PaymentForm.StartMonthDefaultText', 'Please Select ...')),
            'startYear' => DropdownField::create(
                $this->getFieldName('startYear'),
                _t('PaymentForm.StartYear', 'Year'),
                array_combine($startrange, $startrange),
                $year
            )->setHasEmptyDefault(true)->setEmptyString(_t('PaymentForm.StartYearDefaultText', 'Please Select ...')),
            'expiryMonth' => DropdownField::create(
                $this->getFieldName('expiryMonth'),
                _t('PaymentForm.ExpiryMonth', 'Month'),
                $months
            )->setHasEmptyDefault(true)->setEmptyString(_t('PaymentForm.ExpiryMonthDefaultText', 'Please Select ...')),
            'expiryYear' => DropdownField::create(
                $this->getFieldName('expiryYear'),
                _t('PaymentForm.ExpiryYear', 'Year'),
                array_combine($expiryrange, $expiryrange),
                $year
            )->setHasEmptyDefault(true)->setEmptyString(_t('PaymentForm.ExpiryYearDefaultText', 'Please Select ...')),
            'cvv' => TextField::create(
                $this->getFieldName('cvv'),
                _t('PaymentForm.CVV', 'Security Code')
            )->setMaxLength(5),
            'issueNumber' => TextField::create(
                $this->getFieldName('issueNumber'),
                _t('PaymentForm.IssueNumber', 'Issue Number')
            )
        );

        $this->cullForGateway($fields);
        //optionally group date fields
        if ($this->groupdatefields) {
            if (isset($fields[ 'startMonth' ]) && isset($fields[ 'startYear' ])) {
                $fields[ 'startMonth' ] = FieldGroup::create(
                    _t('PaymentForm.Start', 'Start'),
                    $fields[ 'startMonth' ],
                    $fields[ 'startYear' ]
                )->addExtraClass('card_startyear');
                unset($fields[ 'startYear' ]);
            }
            if (isset($fields[ 'expiryMonth' ]) && isset($fields[ 'expiryYear' ])) {
                $fields[ 'expiryMonth' ] = FieldGroup::create(
                    _t('PaymentForm.Expiry', 'Expiry'),
                    $fields[ 'expiryMonth' ],
                    $fields[ 'expiryYear' ]
                )->addExtraClass('card_expiry');
                unset($fields[ 'expiryYear' ]);
            }
        }

        return FieldList::create($fields);
    }

    /**
     * Get a list of supported credit-card brands.
     *
     * @return array
     */
    public function getCardTypes()
    {
        $card = new CreditCard();
        $brands = $card->getSupportedBrands();

        foreach ($brands as $brand => $x) {
            $brands[$brand] = _t('CreditCard.'.strtoupper($brand), $brand);
        }

        return $brands;
    }

    /**
     * Get billing address fields
     *
     * @return FieldList
     */
    public function getBillingFields()
    {
        $fields = array(
            'billingAddress1' => TextField::create(
                $this->getFieldName('billingAddress1'),
                _t('PaymentForm.BillingAddress1', 'Address')
            ),
            'billingAddress2' => TextField::create(
                $this->getFieldName('billingAddress2'),
                _t('PaymentForm.BillingAddress2', 'Address line 2')
            ),
            'city' => TextField::create(
                $this->getFieldName('billingCity'),
                _t('PaymentForm.BillingCity', 'City')
            ),
            'postcode' => TextField::create(
                $this->getFieldName('billingPostcode'),
                _t('PaymentForm.BillingPostcode', 'Postcode')
            ),
            'state' => TextField::create(
                $this->getFieldName('billingState'),
                _t('PaymentForm.BillingState', 'State')
            ),
            'country' => TextField::create(
                $this->getFieldName('billingCountry'),
                _t('PaymentForm.BillingCountry', 'Country')
            ),
            'phone' => TextField::create(
                $this->getFieldName('billingPhone'),
                _t('PaymentForm.BillingPhone', 'Phone')
            )
        );

        $this->cullForGateway($fields);

        return FieldList::create($fields);
    }

    /**
     * Get shipping address fields.
     *
     * @return FieldList
     */
    public function getShippingFields()
    {
        $fields = array(
            'shippingAddress1' => TextField::create(
                $this->getFieldName('shippingAddress1'),
                _t('PaymentForm.ShippingAddress1', 'Shipping Address')
            ),
            'shippingAddress2' => TextField::create(
                $this->getFieldName('shippingAddress2'),
                _t('PaymentForm.ShippingAddress2', 'Shipping Address 2')
            ),
            'city' => TextField::create(
                $this->getFieldName('shippingCity'),
                _t('PaymentForm.ShippingCity', 'Shipping City')
            ),
            'postcode' => TextField::create(
                $this->getFieldName('shippingPostcode'),
                _t('PaymentForm.ShippingPostcode', 'Shipping Postcode')
            ),
            'state' => TextField::create(
                $this->getFieldName('shippingState'),
                _t('PaymentForm.ShippingState', 'Shipping State')
            ),
            'country' => TextField::create(
                $this->getFieldName('shippingCountry'),
                _t('PaymentForm.ShippingCountry', 'Shipping Country')
            ),
            'phone' => TextField::create(
                $this->getFieldName('shippingPhone'),
                _t('PaymentForm.ShippingPhone', 'Shipping Phone')
            )
        );

        $this->cullForGateway($fields);

        return FieldList::create($fields);
    }

    /**
     * Get email fields.
     *
     * @return FieldList
     */
    public function getEmailFields()
    {
        $fields = array(
            'email' => EmailField::create($this->getFieldName('email'), _t('PaymentForm.Email', 'Email'))
        );

        $this->cullForGateway($fields);

        return FieldList::create($fields);
    }

    /**
     * Get company fields
     *
     * @return FieldList
     */
    public function getCompanyFields()
    {
        $fields = array(
            'company' => TextField::create($this->getFieldName('company'), _t('PaymentForm.Company', 'Company'))
        );

        $this->cullForGateway($fields);

        return FieldList::create($fields);
    }

    /**
     * Clear all fields that are not required by the gateway. Does nothing if gateway is null
     * @param $fields
     * @param array $defaults
     */
    protected function cullForGateway(&$fields, $defaults = array())
    {
        if (!$this->gateway) {
            return;
        }

        $selected = array_merge($defaults, GatewayInfo::requiredFields($this->gateway));
        foreach ($fields as $name => $field) {
            if (!in_array($name, $selected)) {
                unset($fields[$name]);
            }
        }
    }

    /**
     * Attempts to find a custom field name and/or prefix defined in rename.yml, otherwise returns the same input
     * that it was given
     *
     * @param string|array $defaultName The default name of the field
     *
     * @return string|array
     */
    public function getFieldName($defaultName)
    {
        if (is_array($defaultName)) {
            return $this->getFieldNames($defaultName);
        }

        if (isset($this->renamemap[$defaultName])) {
            return $this->renamemap[$defaultName];
        }

        return $defaultName;
    }

    /**
     * Batch support for getFieldName()
     *
     * @param array $defaultNames
     *
     * @return array
     */
    public function getFieldNames(array $defaultNames)
    {
        $stack = array();

        if (empty($defaultNames)) {
            // throw user_error?
            return $stack;
        }

        foreach ($defaultNames as $defaultName) {
            $stack[] = $this->getFieldName($defaultName);
        }

        return $stack;
    }

    /**
     * Normalizes form data keys to map to their respective Omnipay parameters (in other words: reverses the effects
     * from the custom field name support)
     *
     * @param array $data The form data consisting of key value pairs
     *
     * @return array
     */
    public function normalizeFormData(array $data)
    {
        if (empty($data)) {
            return $data;
        }

        $renameMap = array_flip($this->renamemap);

        foreach ($renameMap as $customName => $defaultName) {
            if (array_key_exists($customName, $data)) {
                $value = $data[$customName];
                unset($data[$customName]);
                $data[$defaultName] = $value;
            }
        }

        return $data;
    }

    /**
     * Fetches custom name from the rename map, or returns false
     *
     * @param string $defaultName The original name of the field
     *
     * @return bool|string Returns false if no custom name has been defined, otherwise returns the custom name
     */
    private function getGlobalFieldName($defaultName)
    {
        $renameMap = $this->config()->rename;

        if (is_array($renameMap) && array_key_exists($defaultName, $renameMap)) {
            return $renameMap[ $defaultName ];
        }

        return false;
    }

    /**
     * Fetches custom name for a gateway field from the rename map, or returns false
     *
     * @param      $defaultName
     * @param null $gateway Optional, will default to current gateway if instantiated
     *
     * @return bool|string Returns false if no custom gateway field name has been defined, otherwise returns the custom
     *                     name
     */
    private function getGatewayFieldName($defaultName, $gateway = null)
    {
        if (!$gateway) {
            if (!$this->gateway) {
                return false;
            }

            return $this->getGatewayFieldName($defaultName, $this->gateway);
        }

        $renameMap = $this->config()->rename;

        if (is_array($renameMap) && array_key_exists($gateway, $renameMap)) {
            $gatewayMap = $renameMap[ $gateway ];
            if (array_key_exists($defaultName, $gatewayMap)) {
                return $gatewayMap[ $defaultName ];
            }
        }

        return false;
    }

    /**
     * Builds the rename map which is used as a lookup table for normalizeFieldData()
     *
     * @return void
     */
    private function buildRenameMap()
    {
        $renameMap = $this->config()->rename;

        if (!$renameMap) {
            return;
        }

        $prefix = $this->getGatewayFieldName('prefix');
        // if a prefix has already been defined in the gateway namespace, continue using it
        // if not, and we have a global prefix, use that.
        // if not, $prefix becomes an empty string
        $prefix = ($prefix) ? $prefix : ($this->getGlobalFieldName('prefix') ?: '');

        foreach ($this->config()->whitelist as $defaultName) {
            // Gateway Rename Support
            if (array_key_exists($this->gateway, $renameMap)) {
                if ($this->getGlobalFieldName($this->gateway) && $customName = $this->getGatewayFieldName($defaultName)) {
                    // we have a gateway defined custom field name for $defaultName
                    $this->renamemap[$defaultName] =  $prefix . $customName;
                    continue;
                }

                // no gateway defined custom field name was found for $defaultName, continuing will fallback to the global map
            }

            //Global Rename
            if ($customName = $this->getGlobalFieldName($defaultName)) {
                $this->renamemap[$defaultName] =  $prefix . $customName;
                continue;
            }

            // at this point, no defined custom field names were found in either the global or gateway namespace of the
            // rename.yml configuration file, return the input as is with our prefix prepended if it has been defined
            $this->renamemap[$defaultName] = $prefix . $defaultName;
        }
    }
}
