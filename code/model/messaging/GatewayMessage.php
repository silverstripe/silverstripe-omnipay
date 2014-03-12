<?php
class GatewayMessage extends PaymentMessage{

	private static $db = array(
		"Gateway" => "Varchar",
		"Identifier" => "Varchar", //local id
		"Reference" => "Varchar", //remote id
		"Code" => "Varchar"
	);

	private static $summary_fields = array(
		'Type','Identifier','Reference','Message','Code'
	);

	private static $indexes = array(
		'Identifier' => true,
	);

	/**
	 * Only allow setting identifier, if one doesn't exist yet.
	 * @param string $id identifier
	 */
	public function setIdentifier($id) {
		if (!$this->Identifier) {
			$this->setField('Identifier', $id);
		}
	}

	/**
	 * Generate a unique url-friendly identifier, if one doesn't exist yet.
	 * @return string|null the new identifier, if created.
	 */
	public function generateIdentifier() {
		$generator = Injector::inst()->create('RandomGenerator');
		if (!$this->Identifier) {
			$id = $this->PaymentID . '-' . substr($generator->randomToken(), 0, 30);
			while (self::get()->filter('Identifier', $id)->exists()) {
				$id = $this->PaymentID . '-' . substr($generator->randomToken(), 0, 30);
			}
			$this->Identifier = $id;
			return $id;
		}
		return null;
	}

}