<?php

class GatewayFieldsFactoryTest extends SapphireTest{

	function testFieldGroups() {

		$factory = new GatewayFieldsFactory(
			"Dummy", array(
				'Card',
				'Billing',
				'Shipping',
				'Company',
				'Email'
			));

		$fields = $factory->getFields();

		//TODO: assertions

	}

}