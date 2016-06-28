<?php

namespace SilverStripe\Omnipay;

use Omnipay\Common\CreditCard;

/**
 * Helper for generating gateway fields, based on best practices.
 *
 * @package payment
 */
class GatewayFieldsFactory
{

    protected $fieldgroups = array(
        'Card',
        'Billing',
        'Shipping',
        'Company',
        'Email'
    );

    protected $gateway;
    protected $groupdatefields = true;

    /**
     * GatewayFieldsFactory constructor.
     * @param string|null $gateway the gateway to create fields for. @see setGateway
     * @param array $fieldgroups the field-groups to create
     */
    public function __construct($gateway = null, $fieldgroups = null)
    {
        $this->gateway = $gateway;
        $this->setFieldGroups($fieldgroups);
    }

    /**
     * The field groups to create.
     * An array with field-groups to create. Valid entries are: `'Card', 'Billing', 'Shipping', 'Company', 'Email'`.
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
     * If a gateway is given, only the required fields of that gateway will be returned! If the gateway isn't set, all
     * fields will be returned.
     * @param string|null $gateway the gateway to create fields for.
     * @return $this
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;

        return $this;
    }

    /**
     * Get all the fields from the defined Field-Groups (via constructor or @see setFieldGroups)
     * @return \FieldList
     */
    public function getFields()
    {
        $fields = \FieldList::create();
        foreach ($this->fieldgroups as $group) {
            if (method_exists($this, 'get'.$group.'Fields')) {
                $fields->merge($this->{'get'.$group.'Fields'}());
            }
        }

        return $fields;
    }

    /**
     * Get Credit-Card fields
     * @return \FieldList
     */
    public function getCardFields()
    {
        $months = array();
        //generate list of months
        for ($x = 1; $x <= 12; $x++) {
            $months[$x] = date('m - F', mktime(0, 0, 0, $x, 1));
        }
        $year = date('Y');
        $range = 5;
        $startrange = range(date('Y', strtotime("-$range years")), $year);
        $expiryrange = range($year, date('Y', strtotime("+$range years")));

        $fields = array(
            'type' => \DropdownField::create('type', _t('PaymentForm.Type', 'Type'), $this->getCardTypes()),
            'name' => \TextField::create('name', _t('PaymentForm.Name', 'Name on Card')),
            'number' => \TextField::create('number', _t('PaymentForm.Number', 'Card Number'))
                            ->setDescription(_t('PaymentForm.NumberDescription', 'no dashes or spaces')),
            'startMonth' => \DropdownField::create('startMonth', _t('PaymentForm.StartMonth', 'Month'), $months),
            'startYear' => \DropdownField::create('startYear', _t('PaymentForm.StartYear', 'Year'),
                                array_combine($startrange, $startrange), $year
                            ),
            'expiryMonth' => \DropdownField::create('expiryMonth', _t('PaymentForm.ExpiryMonth', 'Month'), $months),
            'expiryYear' => \DropdownField::create('expiryYear', _t('PaymentForm.ExpiryYear', 'Year'),
                                array_combine($expiryrange, $expiryrange), $year
                            ),
            'cvv' => \TextField::create('cvv', _t('PaymentForm.CVV', 'Security Code'))
                            ->setMaxLength(5),
            'issueNumber' => \TextField::create('issueNumber', _t('PaymentForm.IssueNumber', 'Issue Number'))
        );

        $this->cullForGateway($fields);
        //optionally group date fields
        if ($this->groupdatefields) {
            if (isset($fields['startMonth']) && isset($fields['startYear'])) {
                $fields['startMonth'] = \FieldGroup::create(_t('PaymentForm.Start', 'Start'),
                    $fields['startMonth'], $fields['startYear']
                );
                $fields['startMonth']->addExtraClass('card_startyear');
                unset($fields['startYear']);
            }
            if (isset($fields['expiryMonth']) && isset($fields['expiryYear'])) {
                $fields['expiryMonth'] = \FieldGroup::create(_t('PaymentForm.Expiry', 'Expiry'),
                    $fields['expiryMonth'], $fields['expiryYear']
                );
                $fields['expiryMonth']->addExtraClass('card_expiry');
                unset($fields['expiryYear']);
            }
        }

        return \FieldList::create($fields);
    }

    /**
     * Get a list of supported credit-card brands.
     * This doesn't depend on the
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
     * @return \FieldList
     */
    public function getBillingFields()
    {
        $fields = array(
            'billingAddress1' => \TextField::create('billingAddress1', _t('PaymentForm.BillingAddress1', 'Address')),
            'billingAddress2' => \TextField::create('billingAddress2', _t('PaymentForm.BillingAddress2', 'Address line 2')),
            'city' => \TextField::create('billingCity', _t('PaymentForm.BillingCity', 'City')),
            'postcode' => \TextField::create('billingPostcode', _t('PaymentForm.BillingPostcode', 'Postcode')),
            'state' => \TextField::create('billingState', _t('PaymentForm.BillingState', 'State')),
            'country' => \TextField::create('billingCountry', _t('PaymentForm.BillingCountry', 'Country')),
            'phone' => \PhoneNumberField::create('billingPhone', _t('PaymentForm.BillingPhone', 'Phone'))
        );
        $this->cullForGateway($fields);

        return \FieldList::create($fields);
    }

    /**
     * Get shipping address fields
     * @return \FieldList
     */
    public function getShippingFields()
    {
        $fields = array(
            'shippingAddress1' => \TextField::create(
                'shippingAddress1', _t('PaymentForm.ShippingAddress1', 'Shipping Address')
            ),
            'shippingAddress2' => \TextField::create(
                'shippingAddress2', _t('PaymentForm.ShippingAddress2', 'Shipping Address 2')
            ),
            'city' => \TextField::create('shippingCity', _t('PaymentForm.ShippingCity', 'Shipping City')),
            'postcode' => \TextField::create('shippingPostcode', _t('PaymentForm.ShippingPostcode', 'Shipping Postcode')),
            'state' => \TextField::create('shippingState', _t('PaymentForm.ShippingState', 'Shipping State')),
            'country' => \TextField::create('shippingCountry', _t('PaymentForm.ShippingCountry', 'Shipping Country')),
            'phone' => \PhoneNumberField::create('shippingPhone', _t('PaymentForm.ShippingPhone', 'Shipping Phone'))
        );
        $this->cullForGateway($fields);

        return \FieldList::create($fields);
    }

    /**
     * Get Email fields
     * @return \FieldList
     */
    public function getEmailFields()
    {
        $fields = array(
            'email' => \EmailField::create('email', _t('PaymentForm.Email', 'Email'))
        );
        $this->cullForGateway($fields);

        return \FieldList::create($fields);
    }

    /**
     * Get company fields
     * @return \FieldList
     */
    public function getCompanyFields()
    {
        $fields = array(
            'company' => \TextField::create('company', _t('PaymentForm.Company', 'Company'))
        );
        $this->cullForGateway($fields);

        return \FieldList::create($fields);
    }

    /**
     * Clear all fields that are not required by the gateway. Does nothing if gateway is null
     * @param $fields
     * @param array $defaults
     */
    protected function cullForGateway(&$fields, $defaults = array())
    {
        if (!$this->gateway){
            return;
        }

        $selected = array_merge($defaults, GatewayInfo::requiredFields($this->gateway));
        foreach ($fields as $name => $field) {
            if (!in_array($name, $selected)) {
                unset($fields[$name]);
            }
        }
    }
}
