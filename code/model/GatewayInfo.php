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
	 * @return boolean          [description]
	 */
	public static function is_offsite($gateway){
		$gateway = GatewayFactory::create($gateway);
		return $gateway->supportsCompletePurchase() || $gateway->supportsCompleteAuthorize();
	}

}