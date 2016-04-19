<?php


namespace SilverStripe\Omnipay\Admin\GridField;


/**
 * Abstract baseclass for payment actions
 * @package SilverStripe\Omnipay\Admin\GridField
 */
abstract class GridFieldPaymentAction implements \GridField_ColumnProvider, \GridField_ActionProvider
{
    /**
     * Add a column 'Capture'
     *
     * @param \GridField $gridField
     * @param array $columns
     */
    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }
    }

    /**
     * Return any special attributes that will be used for FormField::create_tag()
     *
     * @param \GridField $gridField
     * @param \DataObject $record
     * @param string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return array('class' => 'col-buttons');
    }

    /**
     * Add the title
     *
     * @param \GridField $gridField
     * @param string $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName == 'Actions') {
            return array('title' => '');
        }
    }

    /**
     * Which columns are handled by this component
     *
     * @param \GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return array('Actions');
    }
}
