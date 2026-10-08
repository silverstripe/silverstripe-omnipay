<?php

namespace SilverStripe\Omnipay;

use SilverStripe\Forms\FieldList;

/**
 * Supplies gateway-specific form fields for {@link GatewayFieldsFactory}.
 */
interface GatewayFieldsProvider
{
    /**
     * When true, the Card field group uses {@link getCardFields()} from this provider
     * instead of {@link GatewayFieldsFactory::getCardFields()}.
     */
    public function providesCardFields(GatewayFieldsFactory $factory): bool;

    /**
     * Replaces the standard credit card fields when {@link providesCardFields} is true.
     */
    public function getCardFields(GatewayFieldsFactory $factory): FieldList;

    /**
     * When not null, replaces the default onsite card requirements from {@link GatewayInfo::requiredFields()}
     * (name, number, expiryMonth, expiryYear, cvv). When null, those defaults are merged as usual.
     *
     * @return list<string>|null
     */
    public function getRequiredCardFieldsForGateway(string $gateway): ?array;
}
