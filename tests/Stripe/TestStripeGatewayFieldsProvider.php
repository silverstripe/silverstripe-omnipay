<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Stripe;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Omnipay\Stripe\StripeGatewayFieldsProvider;
use Stripe\Exception\ApiConnectionException;
use Stripe\PaymentIntent;

/**
 * Doesn't talk to the Stripe API, but records the PaymentIntent parameters instead.
 */
class TestStripeGatewayFieldsProvider extends StripeGatewayFieldsProvider implements TestOnly
{
    /** @var array<string, mixed>|null */
    public static ?array $lastParams = null;

    public static ?string $lastSecretKey = null;

    public static bool $failRequest = false;

    public static function reset(): void
    {
        self::$lastParams = null;
        self::$lastSecretKey = null;
        self::$failRequest = false;
    }

    protected function createPaymentIntent(array $params, string $secretKey): ?PaymentIntent
    {
        self::$lastParams = $params;
        self::$lastSecretKey = $secretKey;

        if (self::$failRequest) {
            throw new ApiConnectionException('Could not connect to Stripe');
        }

        return PaymentIntent::constructFrom([
            'id' => 'pi_123',
            'client_secret' => 'pi_123_secret_abc',
        ]);
    }
}
