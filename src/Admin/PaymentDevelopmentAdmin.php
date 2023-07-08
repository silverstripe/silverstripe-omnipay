<?php

namespace SilverStripe\Omnipay\Admin;

use Omnipay\Omnipay;
use SilverStripe\Dev\Debug;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Dev\DebugView;

/**
 * Development tools for payments
 *
 * @package payment
 */
class PaymentDevelopmentAdmin extends Controller
{
    public function index()
    {
        $renderer = DebugView::create();
        $renderer->renderHeader();
        $renderer->renderInfo('Installed Omnipay Payment Gateways', Director::absoluteBaseURL());
        $types = $this->PaymentTypes();

        //$renderer->renderVariable()

        echo '<table style="font-size:12px;" border="1" cellspacing="0">
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
						<td>Accept Notification</td>
					</tr>
				</thead>
			<tbody>';

        foreach ($types as $gateway) {
            echo '<tr>' .
                '<td>' . $gateway->getShortName() . '</td>' .
                '<td>' . $gateway->getName() . '</td>' .
                '<td>' . ($gateway->supportsPurchase() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsAuthorize() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsCompleteAuthorize() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsCapture() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsCompletePurchase() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsRefund() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsVoid() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsCreateCard() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsDeleteCard() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsUpdateCard() ? 'yes' : '') . '</td>' .
                '<td>' . ($gateway->supportsAcceptNotification() ? 'yes' : '') . '</td>' .
            '</tr>';
            if ($this->request->getVar('defaults')) {
                echo '<tr><td colspan="13">';
                Debug::dump($gateway->getDefaultParameters());
                echo '</td></tr>';
            }
        }
        echo '</tbody></table>';
        $renderer->renderFooter();
    }

    /**
     * Get all available payment types
     */
    private function PaymentTypes()
    {
        $factory = Omnipay::getFactory();
        // since the omnipay gateway factory only returns gateways from the composer.json extra data,
        // we should merge it with user-defined gateways from Payment.allowed_gateways
        $gateways = array_unique(array_merge(
            $factory->all(),
            array_keys(GatewayInfo::getSupportedGateways(false))
        ));

        $supportedGateways = [];

        array_walk($gateways, function ($name, $index) use (&$supportedGateways, &$factory) {
            try {
                $instance = $factory->create($name);
                $supportedGateways[$name] = $instance;
            } catch (\Exception $e) {
            }
        });

        return $supportedGateways;
    }
}
