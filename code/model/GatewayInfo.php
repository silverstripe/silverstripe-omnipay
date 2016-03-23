<?php

/**
 * Provides information about gateways.
 */
use Omnipay\Common\GatewayFactory;

class GatewayInfo
{
    /**
     * Config accessor
     * @return Config_ForClass
     */
    public static function config()
    {
        return Config::inst()->forClass('GatewayInfo');
    }

    /**
     * Get the available configured payment types, optionally with i18n readable names.
     * @param bool $nice make the array values i18n readable.
     * @return array map of gateway short name to translated long name.
     */
    public static function getSupportedGateways($nice = true)
    {
        $allowed = Payment::config()->allowed_gateways;
        if (!is_array($allowed)) {
            //include the manual payment type by default, if no gateways are configured
            $allowed = array("Manual");
        }
        $allowed = array_combine($allowed, $allowed);
        if ($nice) {
            $allowed = array_map('GatewayInfo::niceTitle', $allowed);
        }

        return $allowed;
    }

    /**
     * Get a locale aware title for the given gateway
     * @param string $name gateway short name
     * @return string nice title for the gateway. Uses translations, if available
     */
    public static function niceTitle($name)
    {
        $gateway = null;
        try {
            $factory = new GatewayFactory();
            $gateway = $factory->create($name);
        } catch (Exception $e) {
            /** do nothing */
        }
        return _t(
            "Payment." . $name,
            $gateway ? $gateway->getName() : $name
        );
    }

    /**
     * Find out if the given gateway is supported.
     * @param  string $gateway gateway name to check
     * @return boolean
     */
    public static function isSupported($gateway)
    {
        $gateways = self::getSupportedGateways(false);
        return isset($gateways[$gateway]);
    }

    /**
     * Checks if the given gateway name is an off-site gateway.
     *
     * @param  string $gateway gateway name
     * @throws RuntimeException
     * @return boolean the gateway offsite or not
     */
    public static function isOffsite($gateway)
    {
        $factory = new GatewayFactory();
        $gateway = $factory->create($gateway);
        // Some offsite gateways don't separate between authorize and complete requests,
        // so we need a different way to determine they're off site in the first place
        // without kicking off a purchase request within Omnipay.
        if (method_exists($gateway, 'isOffsite')) {
            return !!$gateway->isOffsite();
        }

        return ($gateway->supportsCompletePurchase() || $gateway->supportsCompleteAuthorize());
    }

    /**
     * Check for special 'manual' payment type.
     * @param  string $gateway
     * @return boolean
     */
    public static function isManual($gateway)
    {
        $manualGateways = Payment::config()->manual_gateways;

        // if not defined in config, set default manual gateway to 'Manual'
        if (!$manualGateways) {
            $manualGateways = array('Manual');
        }

        return in_array($gateway, $manualGateways);
    }

    /**
     * Get the required parameters for a given gateway
     * @param string $gateway gateway name
     * @return array required parameters
     */
    public static function requiredFields($gateway)
    {
        $parameters = self::getParameters($gateway);
        $fields = array();
        if (isset($parameters['required_fields']) && is_array($parameters['required_fields'])) {
            $fields = $parameters['required_fields'];
        }

        //always require the following for on-site gateways (and not manual)
        if (!self::isOffsite($gateway) && !self::isManual($gateway)) {
            $fields = array_merge(
                $fields,
                array('name', 'number', 'expiryMonth', 'expiryYear', 'cvv')
            );
        }

        return $fields;
    }


    /**
     * Get the gateway config-parameters.
     *
     * @param string $gateway the gateway name
     * @return array|null gateway parameters
     */
    public static function getParameters($gateway)
    {
        $params = Payment::config()->parameters;
        if(isset($params[$gateway])){
            return $params[$gateway];
        }
        return null;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Deprecated methods.
    // TODO: Remove with 3.0
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Get the available configured payment types, optionally with i18n readable names.
     * @param bool $nice make the array values i18n readable.
     * @return array map of gateway short name to translated long name.
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use getSupportedGateways
     */
    public static function get_supported_gateways($nice = true)
    {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use getSupportedGateways');
        return self::getSupportedGateways($nice);
    }

    /**
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use niceTitle
     */
    public static function nice_title($name)
    {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use niceTitle');
        return self::niceTitle($name);
    }

    /**
     * Find out if the given gateway is supported.
     * @param  string $gateway gateway name to check
     * @return boolean
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use isSupported
     */
    public static function is_supported($gateway)
    {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use isSupported');
        return self::isSupported($gateway);
    }

    /**
     * Checks if the given gateway name is an off-site gateway.
     *
     * @param  string $gateway gateway name
     * @throws RuntimeException
     * @return boolean the gateway offsite or not
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use isOffsite
     */
    public static function is_offsite($gateway)
    {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use isOffsite');
        return self::isOffsite($gateway);
    }

    /**
     * Check for special 'manual' payment type.
     * @param  string $gateway
     * @return boolean
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use isManual
     */
    public static function is_manual($gateway)
    {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use isManual');
        return self::isManual($gateway);
    }

    /**
     * Get the required parameters for a given gateway
     * @param string $gateway gateway name
     * @return array required parameters
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use requiredFields
     */
    public static function required_fields($gateway)
    {
        Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use requiredFields');
        return self::requiredFields($gateway);
    }
}
