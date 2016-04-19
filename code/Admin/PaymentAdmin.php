<?php

use SilverStripe\Omnipay\Admin\GridField\GridFieldCaptureAction;
use SilverStripe\Omnipay\Admin\GridField\GridFieldRefundAction;
use SilverStripe\Omnipay\Admin\GridField\GridFieldVoidAction;

/**
 * Model admin administration of payments.
 *
 * @package payment
 */
class PaymentAdmin extends ModelAdmin
{

    private static $menu_title = "Payments";
    private static $url_segment = "payments";
    private static $menu_icon = 'omnipay/images/payment-admin.png';
    private static $menu_priority = 1;

    public $showImportForm = false;

    private static $managed_models = array(
        'Payment'
    );

    public function alternateAccessCheck()
    {
        return !$this->config()->hidden;
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        if ($this->modelClass == 'Payment') {
            /** @var GridFieldConfig $cfg */
            if ($cfg = $form->Fields()->fieldByName('Payment')->getConfig()) {
                $cfg->addComponent(new GridFieldCaptureAction(), 'GridFieldEditButton')
                    ->addComponent(new GridFieldRefundAction(), 'GridFieldEditButton')
                    ->addComponent(new GridFieldVoidAction(), 'GridFieldEditButton');
            }
        }

        return $form;
    }

}
