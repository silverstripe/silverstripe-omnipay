<?php

class OmniPage_Controller extends Page_Controller{

	static $allowed_actions = array(
		'Form'
	);

	function index(){
		return array(
			'Title' => 'Make a payment'
		);
	}

	function gateways(){
		$allowed = Config::inst()->forClass('Payment')->allowed_gateways;
		if(empty($allowed)){
			$allowed = Omnipay\Common\GatewayFactory::find();
		}
		$allowed = array_combine($allowed, $allowed);
		$allowed = array_map(function($name) {
	        return Omnipay\Common\GatewayFactory::create($name)->getName();
	    }, $allowed);
		return $allowed;
	}

	function Form(){
		$fields =  new FieldList(
			CurrencyField::create("Amount","Amount",20),
			DropdownField::create("Gateway","Gateway",$this->gateways())
		);
		$actions = new FieldList(
			FormAction::create("submit")
		);
		return new Form($this,"Form",$fields,$actions);
	}

	function submit(){
		$name = $this->request->postVar("Gateway");
		$gateway = Omnipay\Common\GatewayFactory::create($name);
		$configs = Config::inst()->forClass('Payment')->parameters;
		$gateway->initialize($configs[$name]);
		//$settings = $gateway->getParameters();

		//TODO: do actual payment

		return array();
	}

	function complete(){

		//stub

	}

}
