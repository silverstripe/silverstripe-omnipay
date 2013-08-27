<?php

class Payment extends DataObject{

	private static $db = array(
		'Gateway' => 'Varchar(50)', //omnipay 'short name'
		'Amount' => 'Money'
		//Token?
	);

	private static $has_one = array(
		"PaidBy" => "Member"
	);

}