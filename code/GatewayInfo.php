<?php

namespace SilverStripe\Omnipay;

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\GatewayFactory;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;

/**
 * Provides information about gateways.
 *
 * Use this class in YAML to configure your gateway settings.
 * Eg.
 * <code>
 * GatewayInfo:
 *  PayPal_Express:
 *    use_authorize: true
 *    parameters:
 *      username: 'my.user.name'
 *      # more parameters…
 * </code>
 *
 * The following config settings are allowed per gateway:
 * * `is_manual` *boolean*: Set this to true if this gateway should be considered a "Manual" Payment (eg. Invoice)
 * * `is_offsite` *boolean*: Set this to true if this gateway is an offsite gateway (you can force this setting if the automatic detection fails)
 * * `use_authorize` *boolean*: Whether or not this Gateway should prefer authorize over purchase
 * * `use_async_notification` *boolean*: When set to true, this Gateway will receive asynchronous notifications from the Payment provider
 * * `token_key` *string*: Key for the token parameter
 * * `required_fields` *array*: An array of required form-fields
 * * `parameters` *map*: All gateway parameters that will be passed along to the Omnipay Gateway instance
 * * `allow_capture` *boolean*: Whether or not capturing of authorized payments should be allowed. Defaults to true.
 * * `allow_refund` *boolean*: Whether or not refunding of captured payments should be allowed. Defaults to true.
 * * `allow_void` *boolean*: Whether or not voiding of authorized payments should be allowed. Defaults to true.
 */
class GatewayInfo
{
    /**
     * Config accessor
     * @return \Config_ForClass
     */
    public static function config()
    {
        return \Config::inst()->forClass('GatewayInfo');
    }

    /**
     * Get the available configured payment types, optionally with i18n readable names.
     * @param bool $nice make the array values i18n readable.
     * @return array map of gateway short name to translated long name.
     */
    public static function getSupportedGateways($nice = true)
    {
        $allowed = \Payment::config()->allowed_gateways;
        if (!is_array($allowed) || empty($allowed)) {
            throw new InvalidConfigurationException(
                'No allowed gateways configured. Use Payment.allowed_gateways config.'
            );
        }
        $allowed = array_combine($allowed, $allowed);
        if ($nice) {
            $allowed = array_map('\SilverStripe\Omnipay\GatewayInfo::niceTitle', $allowed);
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
        } catch (\Exception $e) {
            /** do nothing */
        }

        if ($legacyTranslation = _t('Payment.' . $name)) {
            \Deprecation::notice(
                '3.0',
                'Gateway name translations should be in Gateway group, eg. Gateway.' . $name,
                \Deprecation::SCOPE_GLOBAL
            );
            return $legacyTranslation;
        }

        return _t(
            'Gateway.' . $name,
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
     * @throws \RuntimeException
     * @return boolean the gateway offsite or not
     */
    public static function isOffsite($gateway)
    {
        if (self::getConfigSetting($gateway, 'is_offsite')) {
            return true;
        }

        $factory = new GatewayFactory();
        $gateway = $factory->create($gateway);
        // Some offsite gateways don't separate between authorize and complete requests,
        // so we need a different way to determine they're off site in the first place
        // without kicking off a purchase request within Omnipay.
        if (method_exists($gateway, 'isOffsite')) {
            return !!$gateway->isOffsite();
        }

        if ($gateway instanceof AbstractGateway) {
            return ($gateway->supportsCompletePurchase() || $gateway->supportsCompleteAuthorize());
        }

        return false;
    }

    /**
     * Check for special 'manual' payment type.
     * @param  string $gateway
     * @return boolean
     */
    public static function isManual($gateway)
    {
        if (self::getConfigSetting($gateway, 'is_manual')) {
            return true;
        }

        $manualGateways = \Payment::config()->manual_gateways;
        if (is_array($manualGateways)) {
            \Deprecation::notice(
                '3.0',
                'Please refrain from using Payment:manual_gateways config. ' .
                'Mark individual gateways with `is_manual` instead (see docs).'
            );
        }

        // if not defined in config, set default manual gateway to 'Manual'
        if (!$manualGateways) {
            $manualGateways = array('Manual');
        }

        return in_array($gateway, $manualGateways);
    }

    /**
     * Check if the given gateway should use authorize payments
     * @param string $gateway the gateway name
     * @return boolean
     */
    public static function shouldUseAuthorize($gateway)
    {
        // Manual gateways are "authorized" by nature
        if (self::isManual($gateway)) {
            return true;
        }

        return self::getConfigSetting($gateway, 'use_authorize') == true;
    }

    /**
     * Check if the given gateway should use asynchronous notifications
     * @param string $gateway the gateway name
     * @return boolean
     */
    public static function shouldUseAsyncNotifications($gateway)
    {
        // Manual gateways can be excluded
        if (self::isManual($gateway)) {
            return false;
        }

        return self::getConfigSetting($gateway, 'use_async_notification') == true;
    }

    /**
     * Whether or not the given gateway should allow voiding of payments
     * @param string $gateway the gateway name
     * @return bool
     */
    public static function allowVoid($gateway)
    {
        $setting = self::getConfigSetting($gateway, 'allow_void');
        // if the setting isn't present, default to true, otherwise check for truthy value
        return ($setting === null || $setting == true);
    }

    /**
     * Whether or not the given gateway should allow capturing of payments
     * @param string $gateway the gateway name
     * @return bool
     */
    public static function allowCapture($gateway)
    {
        $setting = self::getConfigSetting($gateway, 'allow_capture');
        // if the setting isn't present, default to true, otherwise check for truthy value
        return ($setting === null || $setting == true);
    }

    /**
     * Whether or not the given gateway should allow refunding of payments
     * @param string $gateway the gateway name
     * @return bool
     */
    public static function allowRefund($gateway)
    {
        $setting = self::getConfigSetting($gateway, 'allow_refund');
        // if the setting isn't present, default to true, otherwise check for truthy value
        return ($setting === null || $setting == true);
    }

    /**
     * Get the token key value configured for the given gateway
     * @param string $gateway the gateway name
     * @param string $default the default token key if not found in config
     * @return string
     */
    public static function getTokenKey($gateway, $default = 'token')
    {
        $tokenKey = \Payment::config()->token_key;
        if ($tokenKey) {
            \Deprecation::notice(
                '3.0',
                'Please refrain from setting token_key as config parameter of Payment. ' .
                'Use GatewayInfo and set the token key on a gateway basis (see docs).'
            );
        } else {
            $tokenKey = self::getConfigSetting($gateway, 'token_key');
        }

        return is_string($tokenKey) ? $tokenKey : $default;
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
            \Deprecation::notice(
                '3.0',
                'Please refrain from setting required_fields in the gateway parameters. ' .
                'Put the `required_fields` directly under the gateway (see docs).'
            );
            $fields = $parameters['required_fields'];
        } else {
            $requiredFields = self::getConfigSetting($gateway, 'required_fields');
            if (is_array($requiredFields)) {
                $fields = $requiredFields;
            }
        }

        //always require the following for on-site gateways (and not manual)
        if (!self::isOffsite($gateway) && !self::isManual($gateway)) {
            $fields = array_merge(
                $fields,
                array('name', 'number', 'expiryMonth', 'expiryYear', 'cvv')
            );
        }

        return array_unique($fields);
    }

    /**
     * Get the gateway config-parameters.
     *
     * @param string $gateway the gateway name
     * @return array|null gateway parameters
     */
    public static function getParameters($gateway)
    {
        $params = \Payment::config()->parameters;
        if (isset($params[$gateway])) {
            \Deprecation::notice(
                '3.0',
                'Please refrain from setting Gateway parameters under Payment. ' .
                'Use GatewayConfig instead (see docs).'
            );
            return $params[$gateway];
        }

        $params = self::getConfigSetting($gateway, 'parameters');
        return is_array($params) ? $params : null;
    }

    /**
     * Get a single config setting for a gateway
     * @param string $gateway the gateway name
     * @param string $key the config key to get
     * @return mixed
     */
    public static function getConfigSetting($gateway, $key)
    {
        $config = self::config()->get($gateway);
        if (!is_array($config)) {
            return null;
        }

        return isset($config[$key]) ? $config[$key] : null;
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
     * @codeCoverageIgnore
     */
    public static function get_supported_gateways($nice = true)
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use getSupportedGateways');
        return self::getSupportedGateways($nice);
    }

    /**
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use niceTitle
     * @codeCoverageIgnore
     */
    public static function nice_title($name)
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use niceTitle');
        return self::niceTitle($name);
    }

    /**
     * Find out if the given gateway is supported.
     * @param  string $gateway gateway name to check
     * @return boolean
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use isSupported
     * @codeCoverageIgnore
     */
    public static function is_supported($gateway)
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use isSupported');
        return self::isSupported($gateway);
    }

    /**
     * Checks if the given gateway name is an off-site gateway.
     *
     * @param  string $gateway gateway name
     * @throws \RuntimeException
     * @return boolean the gateway offsite or not
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use isOffsite
     * @codeCoverageIgnore
     */
    public static function is_offsite($gateway)
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use isOffsite');
        return self::isOffsite($gateway);
    }

    /**
     * Check for special 'manual' payment type.
     * @param  string $gateway
     * @return boolean
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use isManual
     * @codeCoverageIgnore
     */
    public static function is_manual($gateway)
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use isManual');
        return self::isManual($gateway);
    }

    /**
     * Get the required parameters for a given gateway
     * @param string $gateway gateway name
     * @return array required parameters
     * @deprecated 3.0 Snake-case methods will be deprecated with 3.0, use requiredFields
     * @codeCoverageIgnore
     */
    public static function required_fields($gateway)
    {
        \Deprecation::notice('3.0', 'Snake-case methods will be deprecated with 3.0. Use requiredFields');
        return self::requiredFields($gateway);
    }
}
