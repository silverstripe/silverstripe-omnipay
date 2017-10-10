<?php

namespace SilverStripe\Omnipay\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\Queries\SQLSelect;

class MigratePaymentTask extends BuildTask
{
    protected $title = "Migrate Payments";
    protected $description = "Update payment records from old SilverStripe payment modul. See ominpay README!";

    protected $count = 0;

    public function run($request)
    {
        $query = SQLSelect::create("*", Payment::singleton()->baseTable());
        foreach ($query->execute() as $record) {
            if ($this->migrationRequired($record)) {
                $this->migrateRecord($record);
            }
        }
        if ($this->count > 0) {
            echo "Successfully migrated $this->count payments";
        } else {
            echo "No migration needed";
        }
    }

    protected function migrationRequired($record)
    {
        return $record['ClassName'] !== "Payment";
    }

    protected function migrateRecord($record)
    {
        $payment = Payment::create();
        $payment->update($record);
        $payment->Status = "Created";
        $payment->ClassName = "Payment";
        $payment->MoneyAmount = $record['AmountAmount'];
        $payment->MoneyCurrency = $record['AmountCurrency'];

        $payment->Gateway = $this->classToGateway($record['ClassName']);
        $statusmap = array(
            'Incomplete' => 'Created',
            'Success' => 'Captured',
            'Failure' => 'Void',
            'Pending' => 'Authorized',
            '' => 'Created'
        );
        $payment->Status = $statusmap[$record['Status']];
        $payment->write();
        $this->count++;
    }

    protected function classToGateway($classname)
    {
        $gatewaymap = array(
            "ChequePayment" => "Manual",
            "DPSPayment" => "PaymentExpress_PxPay",
            "EwayXMLPayment" => "Eway_Rapid",
            "PayPalExpressCheckoutPayment" => "PayPal_Express",
            "PaystationHostedPayment" => "Paystation_Hosted",
            "WorldpayPayment" => "WorldPay"
        );
        if (isset($gatewaymap[$classname])) {
            return $gatewaymap[$classname];
        }

        return $classname;
    }
}
