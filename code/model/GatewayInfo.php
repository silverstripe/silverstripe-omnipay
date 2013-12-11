<?php

/**
 * Provides information about gateways.
 */

use Omnipay\Common\GatewayFactory;

class GatewayInfo{

	/**
	 * Get the available configured payment types, with i18n readable names.
	 * @return array map of gateway short name to translated long name.
	 */
	public static function get_supported_gateways() {
		$allowed = Config::inst()->forClass('Payment')->allowed_gateways;
		$allowed = array_combine($allowed, $allowed);
		$allowed = array_map(function($name) {
			return _t(
				"Payment.".strtoupper($name),
				GatewayFactory::create($name)->getName()
			);
		}, $allowed);

		return $allowed;
	}

	/**
	 * Checks if the given gateway name is an off-site gaeway.
	 * @param  string  $gateway gateway name
	 * @throws RuntimeException
	 * @return boolean the gateway offsite or not
	 */
	public static function is_offsite($gateway){
		$gateway = GatewayFactory::create($gateway);
		return $gateway->supportsCompletePurchase() || $gateway->supportsCompleteAuthorize();
	}

	/**
	 * Get the required parameters for a given gateway
	 * @param string $gateway gateway name
	 * @return array required parameters
	 */
	public static function required_fields($gateway){
		$parameters = Config::inst()->forClass('Payment')->parameters;
		if(!isset($parameters[$gateway]) || !isset($parameters[$gateway]['required_fields'])){
			return array();
		}
		$f = $parameters[$gateway]['required_fields'];
		if(!is_array($f)){
			return array();
		}
		return $f;
	}

}