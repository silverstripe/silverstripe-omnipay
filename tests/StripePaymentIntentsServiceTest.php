<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceFactory;

/**
 * Payments with the Stripe Payment Intents gateway, including 3-D Secure redirects.
 */
class StripePaymentIntentsServiceTest extends FunctionalTest
{
    use PaymentTestTrait {
        setUp as paymentTestSetUp;
    }

    protected static $fixture_file = 'PaymentTest.yml';

    protected $autoFollowRedirection = false;

    protected function setUp(): void
    {
        $this->paymentTestSetUp();

        Payment::config()->set('allowed_gateways', ['Stripe_PaymentIntents']);
        Config::modify()->set(GatewayInfo::class, 'Stripe_PaymentIntents', [
            'token_key' => 'paymentMethod',
            'parameters' => [
                'apiKey' => 'sk_test_secret',
                'stripe_publishable_key' => 'pk_test_publishable',
            ],
        ]);

        $this->payment = Payment::create()
            ->init('Stripe_PaymentIntents', 20.5, 'NZD')
            ->setSuccessUrl('https://example.com/success')
            ->setFailureUrl('https://example.com/failure');
        $this->payment->write();
    }

    public function testPurchaseWithoutCustomerAction(): void
    {
        $this->mockStripeResponse($this->paymentIntent('succeeded'));

        $response = $this->purchaseService()->initiate(['paymentMethod' => 'pm_123']);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('Captured', $this->payment->Status);

        $request = $this->mockHandler->getLastRequest();
        $this->assertSame('/v1/payment_intents', $request->getUri()->getPath());
        $body = $this->requestBody($request);
        $this->assertSame('pm_123', $body['payment_method']);
        $this->assertSame('true', $body['confirm']);
        $this->assertSame('2050', $body['amount']);
        $this->assertSame('nzd', $body['currency']);
    }

    public function testPurchaseWithThreeDSecure(): void
    {
        $this->mockStripeResponse($this->paymentIntent('requires_action', [
            'next_action' => [
                'type' => 'redirect_to_url',
                'redirect_to_url' => ['url' => 'https://hooks.stripe.com/3d_secure/start'],
            ],
        ]));

        $response = $this->purchaseService()->initiate(['paymentMethod' => 'pm_123']);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('https://hooks.stripe.com/3d_secure/start', $response->getTargetUrl());
        $this->assertSame('PendingPurchase', $this->payment->Status);
        $this->assertSame(
            'pi_123',
            Payment::get()->byID($this->payment->ID)->TransactionReference,
            'The PaymentIntent is stored, so it can be confirmed when the customer returns'
        );

        // The customer returns from Stripe
        $this->mockStripeResponse($this->paymentIntent('succeeded'));

        $response = $this->purchaseService()->complete();

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('Captured', $this->payment->Status);
        $this->assertSame(
            '/v1/payment_intents/pi_123/confirm',
            $this->mockHandler->getLastRequest()->getUri()->getPath()
        );
    }

    public function testAuthorizeWithThreeDSecure(): void
    {
        Config::modify()->merge(GatewayInfo::class, 'Stripe_PaymentIntents', ['use_authorize' => true]);

        $this->mockStripeResponse($this->paymentIntent('requires_action', [
            'next_action' => [
                'type' => 'redirect_to_url',
                'redirect_to_url' => ['url' => 'https://hooks.stripe.com/3d_secure/start'],
            ],
        ]));

        $service = $this->factory->getService($this->payment, ServiceFactory::INTENT_AUTHORIZE);
        $response = $service->initiate(['paymentMethod' => 'pm_123']);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('PendingAuthorization', $this->payment->Status);
        $this->assertSame('pi_123', $this->payment->TransactionReference);

        $this->mockStripeResponse($this->paymentIntent('requires_capture'));

        $service = $this->factory->getService($this->payment, ServiceFactory::INTENT_AUTHORIZE);
        $response = $service->complete();

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('Authorized', $this->payment->Status);
        $this->assertSame(
            '/v1/payment_intents/pi_123/confirm',
            $this->mockHandler->getLastRequest()->getUri()->getPath()
        );
    }

    public function testFailedThreeDSecure(): void
    {
        $this->payment->Status = 'PendingPurchase';
        $this->payment->TransactionReference = 'pi_123';
        $this->payment->write();

        // 3-D Secure failed: Stripe detaches the payment method, so the PaymentIntent can't be confirmed
        $this->mockStripeResponse([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'payment_intent_unexpected_state',
                'message' => 'You cannot confirm this PaymentIntent because it\'s missing a payment method.',
            ],
        ], 400);

        $response = $this->purchaseService()->complete();

        $this->assertTrue($response->isError());
        $this->assertSame('PendingPurchase', $this->payment->Status);
    }

    public function testPaymentIntentReferenceCannotBeSetFromData(): void
    {
        $this->payment->Status = 'PendingPurchase';
        $this->payment->TransactionReference = 'pi_123';
        $this->payment->write();

        $this->mockStripeResponse($this->paymentIntent('succeeded'));

        $this->purchaseService()->complete(['paymentIntentReference' => 'pi_other']);

        $this->assertSame(
            '/v1/payment_intents/pi_123/confirm',
            $this->mockHandler->getLastRequest()->getUri()->getPath()
        );
    }

    public function testCompleteWithoutPaymentIntent(): void
    {
        $this->payment->Status = 'PendingPurchase';
        $this->payment->write();

        $response = $this->purchaseService()->complete(['paymentIntentReference' => 'pi_other']);

        $this->assertTrue($response->isError());
        $this->assertSame('PendingPurchase', $this->payment->Status);
        $this->assertNull($this->mockHandler->getLastRequest(), 'No request was sent to Stripe');
    }

    private function purchaseService()
    {
        return $this->factory->getService($this->payment, ServiceFactory::INTENT_PURCHASE);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mockStripeResponse(array $data, int $status = 200): void
    {
        $this->mockHandler->append(new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR)
        ));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function paymentIntent(string $status, array $data = []): array
    {
        return array_merge([
            'id' => 'pi_123',
            'object' => 'payment_intent',
            'amount' => 2050,
            'currency' => 'nzd',
            'status' => $status,
            'payment_method' => 'pm_123',
            'capture_method' => 'automatic',
            'confirmation_method' => 'manual',
        ], $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(RequestInterface $request): array
    {
        parse_str((string) $request->getBody(), $body);
        return $body;
    }
}
