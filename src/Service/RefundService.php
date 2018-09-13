<?php

namespace SilverStripe\Omnipay\Service;

use Omnipay\Common\Exception\OmnipayException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\InvalidParameterException;
use SilverStripe\Omnipay\Exception\MissingParameterException;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Helper\ErrorHandling;
use SilverStripe\Omnipay\Helper\PaymentMath;
use SilverStripe\Omnipay\Model\Message;
use SilverStripe\Omnipay\Model\Payment;

class RefundService extends NotificationCompleteService
{
    protected $startState = 'Captured';

    protected $endState = 'Refunded';

    protected $pendingState = 'PendingRefund';

    protected $requestMessageType = Message\RefundRequest::class;

    protected $errorMessageType = Message\RefundError::class;

    /**
     * Return money to the previously charged credit card.
     *
     * If the transaction-reference of the payment to refund is known, pass it via $data as
     * `transactionReference` parameter. Otherwise the service will look up the previous reference
     * from the payment itself.
     * If there's no transaction-reference to be found, this method will raise an exception.
     *
     * You can issue partial refunds (if the gateway supports it) by passing an `amount` parameter in the $data
     * array. If the amount given is not a number, or if it exceeds the total amount of the payment, an exception
     * will be raised.
     *
     * @inheritdoc
     * @throws MissingParameterException if no transaction reference can be found from messages or parameters
     * @throws InvalidParameterException if the amount parameter was invalid
     */
    public function initiate($data = array())
    {
        if (!$this->payment->canRefund()) {
            throw new InvalidConfigurationException('Refunding of this payment not allowed.');
        }

        if (!$this->payment->isInDB()) {
            $this->payment->write();
        }

        $reference = null;

        // If the gateway isn't manual, we need a transaction reference to refund a payment
        if (!GatewayInfo::isManual($this->payment->Gateway)) {
            if (!empty($data['transactionReference'])) {
                $reference = $data['transactionReference'];
            } elseif (!empty($data['receipt'])) { // legacy code?
                $reference = $data['receipt'];
            } else {
                $reference = $this->payment->TransactionReference;
            }

            if (empty($reference)) {
                throw new MissingParameterException('transactionReference not found and is not set as parameter');
            }
        }

        $gateway = $this->oGateway();
        if (!$gateway->supportsRefund()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support refunds', $this->payment->Gateway)
            );
        }

        $amount = $this->payment->MoneyAmount;
        $isPartial = false;

        if (!empty($data['amount'])) {
            $amount = $data['amount'];
            if (!is_numeric($amount)) {
                throw new InvalidParameterException('The "amount" parameter has to be numeric.');
            }

            if (!($amount > 0)) {
                throw new InvalidParameterException('The "amount" parameter has to be positive.');
            }

            $compare = PaymentMath::compare($this->payment->MoneyAmount, $amount);
            if ($compare === -1) {
                throw new InvalidParameterException('The "amount" to refund cannot exceed the captured amount.');
            }

            $isPartial = $compare === 1;
        }

        if ($isPartial && !$this->payment->canRefund(null, true)) {
            throw new InvalidParameterException('This payment cannot be partially refunded (unsupported by gateway).');
        }

        $gatewayData = array_merge(
            $data,
            array(
                'amount' => (float)$amount,
                'currency' => $this->payment->MoneyCurrency,
                'transactionReference' => $reference,
                'notifyUrl' => $this->getEndpointUrl('notify')
            )
        );

        $this->extend('onBeforeRefund', $gatewayData);
        $request = $this->oGateway()->refund($gatewayData);
        $this->extend('onAfterRefund', $request);

        $message = $this->createMessage($this->requestMessageType, $request);
        $message->write();

        try {
            $response = $this->response = $request->send();
        } catch (OmnipayException $e) {
            $this->createMessage($this->errorMessageType, $e);
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        ErrorHandling::safeExtend($this, 'onAfterSendRefund', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isAwaitingNotification()) {
            if ($isPartial) {
                $this->createPartialPayment(PaymentMath::multiply($amount, '-1'), $this->pendingState);
            }
            $this->payment->Status = $this->pendingState;
            $this->payment->write();
        } else {
            if ($serviceResponse->isError()) {
                $this->createMessage($this->errorMessageType, $response);
            } else {
                if ($isPartial) {
                    $this->createPartialPayment(PaymentMath::multiply($amount, '-1'), $this->pendingState);
                }
                $this->markCompleted($this->endState, $serviceResponse, $response);
            }
        }

        return $serviceResponse;
    }

    protected function markCompleted($endStatus, ServiceResponse $serviceResponse, $gatewayMessage)
    {
        // Get partial payments
        $partials = $this->payment->getPartialPayments()->filter('Status', $this->pendingState);

        if ($partials->count() > 0) {
            $i = 0;
            $total = $this->payment->MoneyAmount;
            /** @var Payment $payment */
            foreach ($partials as $payment) {
                // only the first, eg. most recent payment should be considered valid. All others should be set to void
                if ($i === 0) {
                    $total = PaymentMath::add($total, $payment->MoneyAmount);
                    $payment->Status = 'Created';
                    $payment->setAmount(PaymentMath::multiply($payment->MoneyAmount, '-1'));
                    $payment->Status = 'Refunded';
                } else {
                    $payment->Status = 'Void';
                }
                $payment->write();
                $i++;
            }

            // Ugly hack to set the money amount
            $this->payment->Status = 'Created';
            $this->payment->setAmount($total);

            // If not everything was refunded, the payment should still have the "Captured" status
            if ($total > 0) {
                $endStatus = 'Captured';
            }
        }

        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);
        if ($endStatus === 'Captured') {
            $this->createMessage(Message\PartiallyRefundedResponse::class, $gatewayMessage);
        } else {
            $this->createMessage(Message\RefundedResponse::class, $gatewayMessage);
        }

        ErrorHandling::safeExtend($this->payment, 'onRefunded', $serviceResponse);
    }
}
