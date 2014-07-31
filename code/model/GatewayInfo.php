<?php

/**
 * Provides information about gateways.
 */

use Omnipay\Common\GatewayFactory;

class GatewayInfo{

	/**
	 * Get the available configured payment types, optionally with i18n readable names.
	 * @param bool $nice make the array values i18n readable.
	 * @return array map of gateway short name to translated long name.
	 */
	public static function get_supported_gateways($nice = true) {
		$allowed = Config::inst()->forClass('Payment')->allowed_gateways;
		if (!is_array($allowed)) {
			//include the manual payment type by default, if no gateways are configured
			$allowed = array("Manual");
		}
		$allowed = array_combine($allowed, $allowed);
		if ($nice) {
			$allowed = array_map('GatewayInfo::nice_title', $allowed);
		}

		return $allowed;
	}

	public static function nice_title($name) {
		$gateway = null;
		try {
			$factory = new GatewayFactory();
			$gateway = $factory->create($name);
		} catch (Exception $e) {
			/** do nothing */
		}
		return _t(
			"Payment.".$name,
			$gateway ? $gateway->getName() : $name
		);
	}

	/**
	 * Find out if the given gateway is supported.
	 * @param  string  $gateway gateway name to check
	 * @return boolean
	 */
	public static function is_supported($gateway) {
		$gateways = self::get_supported_gateways(false);
		return isset($gateways[$gateway]);
	}

	/**
	 * Checks if the given gateway name is an off-site gateway.
	 * 
	 * @param  string  $gateway gateway name
	 * @throws RuntimeException
	 * @return boolean the gateway offsite or not
	 */
	public static function is_offsite($gateway) {
		$factory = new GatewayFactory;
		$gateway = $factory->create($gateway);
		return (
			($gateway->supportsCompletePurchase() || $gateway->supportsCompleteAuthorize())
			// Some offsite gateways don't separate between authorize and complete requests,
			// so we need a different way to determine they're off site in the first place
			// without kicking off a purchase request within omnipay.
			|| (method_exists($gateway, 'isOffsite') && $gateway->isOffsite())
		);
	}

	/**
	 * Check for special 'manual' payment type.
	 * @param  string  $gateway [description]
	 * @return boolean          [description]
	 */
	public static function is_manual($gateway) {
		return $gateway === 'Manual';
	}

	/**
	 * Get the required parameters for a given gateway
	 * @param string $gateway gateway name
	 * @return array required parameters
	 */
	public static function required_fields($gateway) {
		$parameters = Config::inst()->forClass('Payment')->parameters;
		$fields = array();
		if(isset($parameters[$gateway]['required_fields']) &&
			is_array($parameters[$gateway]['required_fields'])){
				$fields = $parameters[$gateway]['required_fields'];
		}
		//always require the following offsite fields
		if (!self::is_offsite($gateway)) {
			$fields = array_merge(
				$fields,
				array('name','number','expiryMonth','expiryYear','cvv')
			);
		}
		return $fields;
	}

}
