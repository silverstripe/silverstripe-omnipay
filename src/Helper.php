<?php

namespace SilverStripe\Omnipay;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;

/**
 * Helper methods for the SilverStripe Omnipay Module
 * @package SilverStripe\Omnipay
 */
class Helper
{
    use Configurable;

    const LOGSTYLE_VERBOSE = 'verbose';
    const LOGSTYLE_SIMPLE = 'simple';
    const LOGSTYLE_FULL = 'full';

    /**
     * The Gateway-Data logging style. Can be one of the following:
     *  - 'full': Verbose logging, log all information. This will automatically turn into 'verbose' on a live environment!
     *  - 'verbose': Verbose logging, but strips out sensitive information
     *  - 'simple': Simplified messages
     * @var string
     * @config
     */
    private static $logStyle = 'verbose';

    /**
     * Field-Names that should be removed from the log
     * @var array
     * @config
     */
    private static $loggingBlacklist = [
        'card', 'token', 'cvv'
    ];

    /**
     * Helper Method to safeguard an extend call.
     * It's important that extensions don't interrupt with errors, as payment data/messages might not get written properly!
     *
     * In dev and test environments, exceptions will be thrown!
     *
     * @param mixed $object the object that should run the extension
     * @param string $method the extension method to call
     * @param mixed $a1 optional parameter 1
     * @param mixed $a2 optional parameter 2
     * @param mixed $a3 optional parameter 3
     * @param mixed $a4 optional parameter 4
     * @param mixed $a5 optional parameter 5
     * @param mixed $a6 optional parameter 6
     * @param mixed $a7 optional parameter 7
     * @return array
     * @throws \Exception any exception that occurred (only in dev and test environments)
     */
    public static function safeExtend(
        $object,
        $method,
        &$a1 = null,
        &$a2 = null,
        &$a3 = null,
        &$a4 = null,
        &$a5 = null,
        &$a6 = null,
        &$a7 = null
    ) {
        if (!(method_exists($object, 'extend'))) {
            return [];
        }

        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING & E_USER_WARNING & E_ERROR & E_USER_ERROR & E_RECOVERABLE_ERROR);

        $retVal = array();
        try {
            $retVal = $object->extend($method, $a1, $a2, $a3, $a4, $a5, $a6, $a7);
        } catch (\Exception $ex) {
            self::getLogger()->warn(
                'An error occurred when trying to run extension point: '. $object->class . '->' . $method
            );

            self::getLogger()->warn($ex);

            // In dev and test environments, throw the exception!
            if (Director::isDev() || Director::isTest()) {
                restore_error_handler();
                throw  $ex;
            }
        }

        restore_error_handler();
        return $retVal;
    }

    /**
     * Safeguard a method by catching exceptions/errors that might be thrown and redirect them to the log
     * @param \Closure $method
     * @param string $errorMessage custom message to write to the log
     * @return mixed whatever your closure returns
     * @throws \Exception any exception that occurred (only in dev and test environments)
     */
    public static function safeguard(\Closure $method, $errorMessage)
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING & E_USER_WARNING & E_ERROR & E_USER_ERROR & E_RECOVERABLE_ERROR);

        try {
            $retVal = $method();
            restore_error_handler();
            return $retVal;
        } catch (\Exception $ex) {
            self::getLogger()->warn($errorMessage);
            self::getLogger()->warn($ex);

            // In dev and test environments, throw the exception!
            if (Director::isDev() || Director::isTest()) {
                restore_error_handler();
                throw  $ex;
            }
        }

        restore_error_handler();
    }

    /**
     * Prepare data for logging by cleaning up the data or simplify it.
     * @param mixed $data the incoming data to log
     * @return array processed data for logging
     */
    public static function prepareForLogging($data)
    {
        if (empty($data)) {
            return [];
        }

        // If not an array, wrap it as an array
        if (!is_array($data)) {
            return [$data];
        }

        if (self::config()->logStyle == self::LOGSTYLE_SIMPLE) {
            return array_filter([
                isset($data['Message']) ? $data['Message'] : null,
                isset($data['Code']) ? $data['Code'] : null
            ]);
        }

        if (Director::isLive() || self::config()->logStyle == self::LOGSTYLE_VERBOSE) {
            self::sanitize($data);
        }

        return $data;
    }

    /**
     * Clean out sensitive data, such as credit-card numbers
     * @param array $data
     */
    private static function sanitize(array &$data)
    {
        $blackList = array_combine(self::config()->loggingBlacklist, self::config()->loggingBlacklist);
        array_walk_recursive($data, function (&$value, $key) use ($blackList) {
            if (isset($blackList[$key])) {
                $value = '(sanitized)';
            }
        });
    }

    private static function getLogger()
    {
        return Injector::inst()->get('SilverStripe\Omnipay\Logger');
    }
}
