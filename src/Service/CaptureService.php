<?php

namespace SilverStripe\Omnipay\Service;

use Omnipay\Common\Exception\OmnipayException;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\InvalidParameterException;
use SilverStripe\Omnipay\Exception\MissingParameterException;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Helper\ErrorHandling;
use SilverStripe\Omnipay\Model\Message\CapturedResponse;
use SilverStripe\Omnipay\Model\Message\CaptureError;
use SilverStripe\Omnipay\Model\Message\CaptureRequest;
use SilverStripe\Omnipay\Model\Message\PartiallyCapturedResponse;
use SilverStripe\Omnipay\Helper\PaymentMath;
use SilverStripe\Omnipay\Model\Payment;

/**
 * Service used in tandem with AuthorizeService.
 *
 * This service captures a previously authorized amount
 */
class CaptureService extends NotificationCompleteService
{
    protected $startState = 'Authorized';

    protected $endState = 'Captured';

    protected $pendingState = 'PendingCapture';

    protected $requestMessageType = CaptureRequest::class;

    protected $errorMessageType = CaptureError::class;

    /**
     * Capture a previously authorized payment
     *
     * If the transaction-reference of the payment to capture is known, pass it via $data as
     * `transactionReference` parameter. Otherwise the service will try to look up the reference from previous payment
     * messages.
     *
     * If there's no transaction-reference to be found, this method will raise an exception.
     *
     * You can issue partial captures (if the gateway supports it) by passing an `amount` parameter in the $data
     * array. The amount can also exceed the authorized amount, if the configuration allows it (`max_capture` setting).
     * An amount that exceeds the authorized amount will always be considered as a full capture!
     * If the amount given is not a number, or if it exceeds the total possible capture amount, an exception
     * will be raised.
     *
     * @inheritdoc
     * @throws MissingParameterException if no transaction reference can be found from messages or parameters
     * @throws InvalidParameterException if the amount parameter was invalid
     */
    public function initiate($data = array())
    {
        if (!$this->payment->canCapture()) {
            throw new InvalidConfigurationException('Capture of this payment not allowed.');
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
        if (!$gateway->supportsCapture()) {
            throw new InvalidConfigurationException(
                sprintf('The gateway "%s" doesn\'t support capture', $this->payment->Gateway)
            );
        }

        $authorized = $amount = $this->payment->MoneyAmount;
        $diff = 0;

        if (!empty($data['amount'])) {
            $amount = $data['amount'];
            if (!is_numeric($amount)) {
                throw new InvalidParameterException('The "amount" parameter has to be numeric.');
            }

            if (!($amount > 0)) {
                throw new InvalidParameterException('The "amount" parameter has to be positive.');
            }

            // check if the amount exceeds the max. amount that can be captured
            if (PaymentMath::compare($this->payment->getMaxCaptureAmount(), $amount) === -1) {
                throw new InvalidParameterException('The "amount" given exceeds the amount that can be captured.');
            }

            $diff = PaymentMath::subtract($amount, $authorized);
        }

        if ($diff < 0 && !$this->payment->canCapture(null, true)) {
            throw new InvalidParameterException('This payment cannot be partially captured (unsupported by gateway).');
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

        $this->extend('onBeforeCapture', $gatewayData);
        $request = $this->oGateway()->capture($gatewayData);
        $this->extend('onAfterCapture', $request);

        $message = $this->createMessage($this->requestMessageType, $request);
        $message->write();

        try {
            $response = $this->response = $request->send();
        } catch (OmnipayException $e) {
            $this->createMessage($this->errorMessageType, $e);
            return $this->generateServiceResponse(ServiceResponse::SERVICE_ERROR);
        }

        ErrorHandling::safeExtend($this, 'onAfterSendCapture', $request, $response);

        $serviceResponse = $this->wrapOmnipayResponse($response);

        if ($serviceResponse->isError()) {
            $this->createMessage($this->errorMessageType, $response);
        } elseif ($serviceResponse->isRedirect() || $serviceResponse->isAwaitingNotification()) {
            if ($diff < 0) {
                $this->createPartialPayment(PaymentMath::multiply($amount, '-1'), $this->pendingState);
            } elseif ($diff > 0) {
                $this->createPartialPayment($diff, $this->pendingState);
            }
            $this->payment->Status = $this->pendingState;
            $this->payment->write();
        } elseif ($serviceResponse->isSuccessful()) {
            if ($diff < 0) {
                $this->createPartialPayment(PaymentMath::multiply($amount, '-1'), $this->pendingState);
            } elseif ($diff > 0) {
                $this->createPartialPayment($diff, $this->pendingState);
            }
            $this->markCompleted($this->endState, $serviceResponse, $response);
        }

        return $serviceResponse;
    }

    protected function markCompleted($endStatus, ServiceResponse $serviceResponse, $gatewayMessage)
    {
        // Get partial payments
        $partials = $this->payment->getPartialPayments()->filter('Status', $this->pendingState);

        if ($partials->count() > 0) {
            $i = 0;
            $total = $originalTotal = $this->payment->MoneyAmount;
            /** @var Payment $payment */
            foreach ($partials as $payment) {
                // only the first, eg. most recent payment should be considered valid. All others should be set to void
                if ($i === 0) {
                    $total = PaymentMath::add($total, $payment->MoneyAmount);

                    // deal with partial capture
                    if ($payment->MoneyAmount < 0) {
                        $payment->Status = 'Created';
                        $payment->setAmount(PaymentMath::multiply($payment->MoneyAmount, '-1'));
                        $payment->Status = 'Captured';
                    } else {
                        // void excess amounts
                        $payment->Status = 'Void';
                    }
                } else {
                    $payment->Status = 'Void';
                }
                $payment->write();
                $i++;
            }

            // Ugly hack to set the money amount
            $this->payment->Status = 'Created';
            $this->payment->setAmount($total);

            // If not everything was captured (partial),
            // the payment should be refunded or still Authorized (in case multiple captures are possible)
            if ($total > 0 && $total < $originalTotal) {
                $endStatus = GatewayInfo::captureMode($this->payment->Gateway) === GatewayInfo::MULTIPLE
                    ? 'Authorized'
                    : 'Refunded';
            }
        }

        parent::markCompleted($endStatus, $serviceResponse, $gatewayMessage);

        if ($endStatus === 'Captured') {
            $this->createMessage(CapturedResponse::class, $gatewayMessage);
        } else {
            $this->createMessage(PartiallyCapturedResponse::class, $gatewayMessage);
        }

        ErrorHandling::safeExtend($this->payment, 'onCaptured', $serviceResponse);
    }
}
