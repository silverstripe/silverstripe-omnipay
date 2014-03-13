<?php
use Omnipay\Common\AbstractGateway;

class GatewayInfoTest extends SapphireTest {

	public function testIsOffsite() {
		$this->assertFalse(GatewayInfo::is_offsite('\GatewayInfoTest_OnsiteGateway'));
		$this->assertTrue(GatewayInfo::is_offsite('\GatewayInfoTest_OffsiteGateway'));
	}

}

class GatewayInfoTest_OnsiteGateway extends AbstractGateway implements TestOnly {

	public function getName() {
		return 'GatewayInfoTest_OnsiteGateway';
	}

	public function getDefaultParameters() {
		return array();
	}

	public function purchase(array $parameters = array()) {}

}

class GatewayInfoTest_OffsiteGateway extends AbstractGateway implements TestOnly {

	public function getName() {
		return 'GatewayInfoTest_OffsiteGateway';
	}

	public function getDefaultParameters() {
		return array();
	}

	public function purchase(array $parameters = array()) {}

	public function isOffsite() {
		return true;
	}

}