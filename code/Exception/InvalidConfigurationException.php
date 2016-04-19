<?php

namespace SilverStripe\Omnipay\Exception;


/**
 * Class InvalidConfigurationException
 *
 * Should be thrown whenever there's an error that could be fixed by properly configuring the module.
 * Example: A gateway is being used for a purchase, while it only supports "authorize"
 *
 * @package SilverStripe\Omnipay
 */
class InvalidConfigurationException extends Exception
{

}
