<?php

namespace SilverStripe\Omnipay\Admin;

/**
 * Development tools for payments
 *
 * @package payment
 */
class PaymentDevelopmentAdmin extends \Controller{

	public function index() {
		$renderer = \DebugView::create();
		$renderer->writeHeader();
		$renderer->writeInfo("Installed Omnipay Payment Gateways", \Director::absoluteBaseURL());
		$types = $this->PaymentTypes();

		echo "<table style=\"font-size:12px;\" border=1 cellspacing=0>
				<thead>
					<tr>
						<td>Short Name</td>
						<td>Full name</td>
						<td>Purchase</td>
						<td>Authorize</td>
						<td>CompleteAuthorize</td>
						<td>Capture</td>
						<td>Complete Purchase</td>
						<td>Refund</td>
						<td>Void</td>
						<td>Create Card</td>
						<td>Delete Card</td>
						<td>Update Card</td>
					</tr>
				</thead>
			<tbody>";

		foreach ($types as $gateway) {
			echo "<tr>".
					"<td>".$gateway->getShortName()."</td>".
					"<td>".$gateway->getName()."</td>".
					"<td>yes</td>". //purchase is always supported
					"<td>".($gateway->supportsAuthorize() ? "yes" : "")."</td>".
					"<td>".($gateway->supportsCompleteAuthorize() ? "yes" : "")."</td>".
					"<td>".($gateway->supportsCapture() ? "yes" : "")."</td>".
					"<td>".($gateway->supportsCompletePurchase() ? "yes" : "")."</td>".
					"<td>".($gateway->supportsRefund() ? "yes" : "")."</td>".
					"<td>".($gateway->supportsVoid() ? "yes" : "")."</td>".
					"<td>".($gateway->supportsCreateCard() ? "yes" : "")."</td>".
					"<td>".($gateway->supportsDeleteCard() ? "yes" : "")."</td>".
					"<td>".($gateway->supportsUpdateCard() ? "yes" : "")."</td>".
			"</tr>";
			if ($this->request->getVar('defaults')) {
				echo "<tr><td colspan=\"11\">";
					var_dump($gateway->getDefaultParameters());
				echo "</td></tr>";
			}
		}
		echo "</tbody></table>";
		$renderer->writeFooter();
	}

	/**
	 * Get all available payment types
	 */
	private function PaymentTypes() {
		$gateways =  \Omnipay\Common\GatewayFactory::find();
		$gateways = array_map(function($name) {
			$factory = new \Omnipay\Common\GatewayFactory;
			return $factory->create($name);
		}, $gateways);
		return $gateways;
	}

}
