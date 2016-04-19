<?php

class GatewayFieldsFactoryTest extends SapphireTest
{

    public function testFieldGroups()
    {
        $factory = new GatewayFieldsFactory(
            "Dummy", array(
                'Card',
                'Billing',
                'Shipping',
                'Company',
                'Email'
            ));

        $fields = $factory->getFields();

        //TODO: assertions
    }
}
