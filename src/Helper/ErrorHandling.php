<?php

namespace SilverStripe\Omnipay\Helper;

use SilverStripe\Control\Director;

/**
 * Error handling methods for the SilverStripe Omnipay Module
 * @package SilverStripe\Omnipay
 */
class ErrorHandling
{
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
            if ($logger = Logging::getLogger()) {
                $logger->warning(
                    'An error occurred when trying to run extension point: '. $object->class . '->' . $method,
                    [
                        'exception' => $ex
                    ]
                );
            }

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
            if ($logger = Logging::getLogger()) {
                $logger->warning($errorMessage, [
                    'exception' => $ex
                ]);
            }

            // In dev and test environments, throw the exception!
            if (Director::isDev() || Director::isTest()) {
                restore_error_handler();
                throw  $ex;
            }
        }

        restore_error_handler();
        return null;
    }
}
