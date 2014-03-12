<?php
class GatewayRequestMessage extends GatewayMessage{

	private static $db = array(
		'SuccessURL' => 'Text',
		'FailureURL' => 'Text'
	);

}