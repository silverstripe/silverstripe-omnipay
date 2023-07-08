<?php

namespace SilverStripe\Omnipay;

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\GatewayFactory;
use SilverStripe\Core\Environment;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Core\Config\Configurable;

/**
 * Provides information about gateways.
 *
 * Use this class in YAML to configure your gateway settings.
 *
 * <code>
 * SilverStripe\Omnipay\GatewayInfo:
 *   PayPal_Express:
 *     use_authorize: true
 *     parameters:
 *       username: 'my.user.name'
 *       # more parameters…
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
 * * `can_capture` *string|boolean*: Set how/if authorized payments can be captured. Defaults to "partial"
 *      Valid values are "off" or `false` (capturing disabled), "full" (can only capture full amounts), "partial" or `true` (can capture partially) and "multiple" which allows multiple partial captures.
 * * `can_refund` *string|boolean*: Set how/if captured payments can be refunded. Defaults to "partial"
 *      Valid values are "off" or `false` (refunding disabled), "full" (can only refund full amounts), "partial" or `true` (can refund partially) and "multiple" which allows multiple partial refunds.
 * * `can_void` *boolean*: Whether or not voiding of authorized payments should be allowed. Defaults to true.
 * * `max_capture` *mixed*: configuration for excess capturing of authorized amounts.
 *
 * Config examples for `max_capture`:
 * <code>
 * ------
 * # Allow excess capture with max. 15%
 * max_capture: '15%'
 * ------
 * # Allow excess capture of max 40 units (default currency)
 * max_capture: 40
 * ------
 * # Allow excess capture of max 20%, but no more than 70
 * # eg. $1000.00 has a max. capture of $1070.00 and $200 has a max. capture of $240.00
 * max_capture:
 *   percent: '20%'
 *   amount: 70
 * ------
 * # Allow excess capture of max 20%, but no more than USD 70 or EUR 60.
 * # The amount field can contain values for all currencies that should be handled by this module
 * max_capture:
 *   percent: '20%'
 *   amount:
 *     USD: 70
 *     EUR: 60
 * </code>
 */
class GatewayInfo
{
    use Configurable;

    const OFF = 'off';
    const FULL = 'full';
    const PARTIAL = 'partial';
    const MULTIPLE = 'multiple';

    /**
     * Get the available configured payment types, optionally with i18n readable names.
     *
     * @param bool $nice make the array values i18n readable.
     *
     * @return array map of gateway short name to translated long name.
     * @throws InvalidConfigurationException
     */
    public static function getSupportedGateways($nice = true)
    {
        $allowed = Payment::config()->allowed_gateways;

        if (!is_array($allowed) || empty($allowed)) {
            throw new InvalidConfigurationException(
                'No allowed gateways configured. Use SilverStripe\Omnipay\Model\Payment.allowed_gateways config.'
            );
        }

        $allowed = array_combine($allowed, $allowed);

        if ($nice) {
            $allowed = array_map('SilverStripe\Omnipay\GatewayInfo::niceTitle', $allowed);
        }

        return $allowed;
    }

    /**
     * Get a locale aware title for the given gateway.
     *
     * @param string $name gateway short name
     *
     * @return string nice title for the gateway. Uses translations, if available
     */
    public static function niceTitle($name)
    {
        if (empty($name)) {
            return '';
        }

        $gateway = null;

        try {
            $factory = new GatewayFactory();
            $gateway = $factory->create($name);
        } catch (\Exception $e) {
            /** do nothing */
        }

        $title = _t(
            'Gateway.' . $name,
            $gateway ? $gateway->getName() : $name
        );

        return ($title) ? $title : $gateway->getName();
    }

    /**
     * Find out if the given gateway is supported.
     * @param  string $gateway gateway name to check
     * @return boolean
     * @throws InvalidConfigurationException
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
        $configValue = self::getConfigSetting($gateway, 'is_offsite');
        // If the value from config is set, return it
        if (is_bool($configValue)) {
            return $configValue;
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
     *
     * @param  string $gateway
     * @return boolean
     */
    public static function isManual($gateway)
    {
        if (self::getConfigSetting($gateway, 'is_manual')) {
            return true;
        }

        if ($gateway === 'Manual') {
            return true;
        }

        return false;
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
        $setting = self::getConfigSetting($gateway, 'can_void');
        // if the setting isn't present, default to true, otherwise check against falsy values
        return ($setting === null || !($setting == false || $setting === 'off' || $setting === 'false'));
    }

    /**
     * The can_capture config setting for the given Gateway
     *
     * @param string $gateway the gateway name
     * @return string "off", "full", "partial" or "multiple"
     */
    public static function captureMode($gateway)
    {
        return self::configToConstant($gateway, 'can_capture');
    }

    /**
     * Whether or not the given gateway should allow capturing of payments
     * @param string $gateway the gateway name
     * @return bool
     */
    public static function allowCapture($gateway)
    {
        return self::configToConstant($gateway, 'can_capture') !== self::OFF;
    }

    /**
     * Whether or not the given gateway should allow partial capturing of payments
     * @param string $gateway the gateway name
     * @return bool
     */
    public static function allowPartialCapture($gateway)
    {
        return (
            self::configToConstant($gateway, 'can_capture') === self::PARTIAL
            || self::configToConstant($gateway, 'can_capture') === self::MULTIPLE
        );
    }

    /**
     * Get the max excess capture percentage for the given gateway.
     *
     * Some payment providers allow capturing a slightly higher amount than was authorized.
     * If $200.00 was authorized and maxExcessCapturePercent returns `15`, you're allowed to capture at max $230.00,
     * unless further restricted by maxExcessCaptureAmount.
     * To get the correct max-capture amount, both maxExcessCapturePercent and maxExcessCaptureAmount
     * have to be considered.
     *
     * @param string $gateway the gateway name
     * @return int|string max excess capture percentage as a number (no percentage sign) or `-1` if the
     *  excess capture percentage is not limited (will only occur if there's a limited fixed amount)
     *  @see maxExcessCaptureAmount
     */
    public static function maxExcessCapturePercent($gateway)
    {
        $setting = self::getConfigSetting($gateway, 'max_capture');
        if (!$setting) {
            return 0;
        }

        $pattern = '/^(\d+)%$/';

        if (!is_array($setting)) {
            if (preg_match($pattern, $setting, $match)) {
                return $match[1];
            }

            if (is_numeric($setting) && $setting > 0) {
                return -1;
            }
        } elseif (!empty($setting['percent'])) {
            if (is_numeric($setting['percent'])) {
                return max(0, $setting['percent']);
            }

            if (preg_match($pattern, $setting['percent'], $match)) {
                return $match[1];
            }
        }

        return 0;
    }

    /**
     * Get the max excess capture amount for the given gateway and an optional currency.
     *
     * Some payment providers allow capturing a slightly higher amount than was authorized.
     * If $200.00 was authorized and maxExcessCaptureAmount returns `70`, you're allowed to capture at max $270.00,
     * unless further restricted by maxExcessCapturePercent.
     * To get the correct max-capture amount, both maxExcessCapturePercent and maxExcessCaptureAmount
     * have to be considered.
     *
     * @param string $gateway the gateway name
     * @param string $currency the currency to look up. Defaults to `null` and is only needed when the config contains
     *  amounts for different currencies.
     * @return int|string the max excess amount or `-1` if the excess amount isn't limited (only limited by percentage)
     */
    public static function maxExcessCaptureAmount($gateway, $currency = null)
    {
        $setting = self::getConfigSetting($gateway, 'max_capture');
        if (!$setting) {
            return 0;
        }

        if (!is_array($setting)) {
            if (preg_match('/^\d+%$/', $setting)) {
                return -1;
            }

            if (is_numeric($setting)) {
                return max(0, $setting);
            }
        } elseif (!empty($setting['amount'])) {
            if (is_numeric($setting['amount'])) {
                return max(0, $setting['amount']);
            }

            if (is_array($setting['amount'])
                && $currency
                && !empty($setting['amount'][$currency])
                && is_numeric($setting['amount'][$currency])
            ) {
                return max(0, $setting['amount'][$currency]);
            }
        }

        return 0;
    }

    /**
     * The can_refund config setting for the given Gateway
     *
     * @param string $gateway the gateway name
     * @return string "off", "full", "partial" or "multiple"
     */
    public static function refundMode($gateway)
    {
        return self::configToConstant($gateway, 'can_refund');
    }

    /**
     * Whether or not the given gateway should allow refunding of payments
     * @param string $gateway the gateway name
     * @return bool
     */
    public static function allowRefund($gateway)
    {
        return self::configToConstant($gateway, 'can_refund') !== self::OFF;
    }

    /**
     * Whether or not the given gateway should allow partial refunding of payments
     * @param string $gateway the gateway name
     * @return bool
     */
    public static function allowPartialRefund($gateway)
    {
        return (
            self::configToConstant($gateway, 'can_refund') === self::PARTIAL
            || self::configToConstant($gateway, 'can_refund') === self::MULTIPLE
        );
    }

    /**
     * Get the token key value configured for the given gateway
     * @param string $gateway the gateway name
     * @param string $default the default token key if not found in config
     * @return string
     */
    public static function getTokenKey($gateway, $default = 'token')
    {
        $tokenKey = self::getConfigSetting($gateway, 'token_key');
        return is_string($tokenKey) ? $tokenKey : $default;
    }

    /**
     * Get the required parameters for a given gateway
     * @param string $gateway gateway name
     * @return array required parameters
     */
    public static function requiredFields($gateway)
    {
        $fields = [];

        $requiredFields = self::getConfigSetting($gateway, 'required_fields');

        if (is_array($requiredFields)) {
            $fields = $requiredFields;
        }

        //always require the following for on-site gateways (and not manual)
        if (!self::isOffsite($gateway) && !self::isManual($gateway)) {
            $fields = array_merge(
                $fields,
                ['name', 'number', 'expiryMonth', 'expiryYear', 'cvv']
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
        $params = self::getConfigSetting($gateway, 'parameters');
        if (!is_array($params)) {
            return null;
        }

        $convertConstants = function (&$config) use (&$convertConstants) {
            foreach ($config as $key => &$value) {
                if (is_array($value)) {
                    $convertConstants($value);
                    continue;
                }

                // Evaluate constants surrounded by back ticks
                // Logic copied from Injector::convertServiceProperty()
                if (preg_match('/^`(?<name>[^`]+)`$/', $value, $matches)) {
                    $envValue = Environment::getEnv($matches['name']);
                    if ($envValue !== false) {
                        $value = $envValue;
                    } elseif (defined($matches['name'])) {
                        $value = constant($matches['name']);
                    } else {
                        $value = null;
                    }
                }
            }
        };

        $convertConstants($params);
        return $params;
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

    /**
     * Helper method to convert a config setting to a predefined constant for values that can have the three states:
     * OFF, FULL, PARTIAL or MULTIPLE
     * @param string $gateway the gateway name
     * @param string $key the config key
     * @return string either "off", "full", "partial" or "multiple"
     */
    protected static function configToConstant($gateway, $key)
    {
        $value = self::getConfigSetting($gateway, $key);
        if ($value === null) {
            return self::PARTIAL;
        }

        if ($value == false || $value === 'off') {
            return self::OFF;
        }

        if ($value === 'full') {
            return self::FULL;
        }

        if ($value === 'multiple') {
            return self::MULTIPLE;
        }

        return self::PARTIAL;
    }
}
