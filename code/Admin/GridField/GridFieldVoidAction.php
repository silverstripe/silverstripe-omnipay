<?php
namespace SilverStripe\Omnipay\Admin\GridField;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Exception\Exception;

/**
 * A GridField button that can be used to void a payment
 *
 * @package SilverStripe\Omnipay\Admin\GridField
 */
class GridFieldVoidAction extends GridFieldPaymentAction
{
    /**
     * Which GridField actions are this component handling
     *
     * @param \GridField $gridField
     * @return array
     */
    public function getActions($gridField)
    {
        return array('voidpayment');
    }

    /**
     *
     * @param \GridField $gridField
     * @param \DataObject $record
     * @param string $columnName
     * @return string|null - the HTML for the column
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        if (!($record instanceof \Payment)) {
            return null;
        }

        if (!$record->canVoid()) {
            return null;
        }

        /** @var \GridField_FormAction $field */
        $field = \GridField_FormAction::create(
            $gridField,
            'VoidPayment' . $record->ID,
            false,
            'voidpayment',
            array('RecordID' => $record->ID)
        )
            ->addExtraClass('gridfield-button-void')
            ->setAttribute('title', _t('GridFieldVoidAction.Title', 'Void Payment'))
            ->setAttribute('data-icon', 'button-void')
            ->setDescription(_t('GridFieldVoidAction.Description', 'Void an authorized payment'));

        return $field->Field();
    }

    /**
     * Handle the actions and apply any changes to the GridField
     *
     * @param \GridField $gridField
     * @param string $actionName
     * @param mixed $arguments
     * @param array $data - form data
     * @return void
     * @throws \ValidationException when there was an error
     */
    public function handleAction(\GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'voidpayment') {
            $item = $gridField->getList()->byID($arguments['RecordID']);
            if (!($item instanceof \Payment)) {
                return;
            }

            /** @var ServiceFactory $factory */
            $factory = ServiceFactory::create();
            $voidService = $factory->getService($item, ServiceFactory::INTENT_VOID);

            try {
                $serviceResponse = $voidService->initiate();
            } catch (Exception $ex){
                throw new \ValidationException(
                    _t('GridFieldVoidAction.VoidError', 'Unable to void payment. An error occurred.'), 0);
            }

            if ($serviceResponse->isError()) {
                throw new \ValidationException(
                    _t('GridFieldVoidAction.VoidError', 'Unable to void payment. An error occurred.'), 0);
            }
        }
    }
}
