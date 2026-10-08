<?php

namespace SilverStripe\Omnipay\Forms;

use Omnipay\Common\CreditCard;
use Omnipay\Common\Helper;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Omnipay\GatewayFieldsFactory;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Validator for forms built with {@link GatewayFieldsFactory}.
 *
 * Checks the fields that are required by the gateway (see {@link GatewayInfo::requiredFields()}) and validates
 * credit-card number (Luhn checksum), expiry date and security code, if these fields are part of the form.
 * Custom field names (see the GatewayFieldsFactory `rename` config) are taken into account.
 *
 * <code>
 * $factory = GatewayFieldsFactory::create($gateway);
 * $form = Form::create($this, 'PaymentForm', $factory->getFields(), $actions, $factory->getValidator());
 * </code>
 */
class GatewayFieldsValidator extends RequiredFieldsValidator
{
    protected GatewayFieldsFactory $factory;

    /**
     * @param GatewayFieldsFactory $factory the factory that was used to create the form fields
     * @param list<string> $required additional required fields (using form field names)
     */
    public function __construct(GatewayFieldsFactory $factory, array $required = [])
    {
        $this->factory = $factory;

        $gateway = $factory->getGateway();
        $gatewayFields = $gateway ? GatewayInfo::requiredFields($gateway) : [];

        parent::__construct(array_merge($factory->getFieldNames($gatewayFields), $required));
    }

    public function getFactory(): GatewayFieldsFactory
    {
        return $this->factory;
    }

    /**
     * @param array<string, mixed> $data
     * @return bool
     */
    public function php($data)
    {
        $valid = parent::php($data);
        $data = $this->factory->normalizeFormData(is_array($data) ? $data : []);

        $number = $this->getSubmittedValue($data, 'number');
        if ($number !== '') {
            $number = preg_replace('/[\s-]+/', '', $number);
            if (!ctype_digit($number) || strlen($number) < 12 || !Helper::validateLuhn($number)) {
                if ($this->addFieldError('number', _t(
                    __CLASS__ . '.InvalidNumber',
                    'Please enter a valid card number'
                ))) {
                    $valid = false;
                }
            }
        }

        $month = $this->getSubmittedValue($data, 'expiryMonth');
        $year = $this->getSubmittedValue($data, 'expiryYear');
        if ($month !== '' && $year !== '') {
            $card = new CreditCard(['expiryMonth' => (int) $month, 'expiryYear' => (int) $year]);
            $expiry = sprintf('%04d%02d', $card->getExpiryYear(), $card->getExpiryMonth());
            if ((int) $month < 1 || (int) $month > 12 || $expiry < date('Ym', DBDatetime::now()->getTimestamp())) {
                if ($this->addFieldError('expiryMonth', _t(
                    __CLASS__ . '.Expired',
                    'The card expiry date is invalid or in the past'
                ))) {
                    $valid = false;
                }
            }
        }

        $cvv = $this->getSubmittedValue($data, 'cvv');
        if ($cvv !== '' && !preg_match('/^\d{3,4}$/', $cvv)) {
            if ($this->addFieldError('cvv', _t(
                __CLASS__ . '.InvalidCvv',
                'Please enter a valid security code'
            ))) {
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * @param array<string, mixed> $data normalized form data
     */
    protected function getSubmittedValue(array $data, string $key): string
    {
        $value = $data[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Add an error for the given field (by its Omnipay name), if the field is part of the form.
     *
     * @return bool whether an error was added
     */
    protected function addFieldError(string $key, string $message): bool
    {
        $fieldName = $this->factory->getFieldName($key);
        if (!$this->form->Fields()->dataFieldByName($fieldName)) {
            return false;
        }

        $this->validationError($fieldName, $message, 'validation');
        return true;
    }
}
