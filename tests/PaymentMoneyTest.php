<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Helper\PaymentMath;
use SilverStripe\Omnipay\Helper\PaymentMoney;

class PaymentMoneyTest extends SapphireTest
{
    #[DataProvider('conversionProvider')]
    public function testConversion(string|int|float $amount, string $currency, string $minorUnits, string $decimal): void
    {
        $money = PaymentMoney::toMoney($amount, $currency);

        $this->assertSame($minorUnits, $money->getAmount());
        $this->assertSame($currency, $money->getCurrency()->getCode());
        $this->assertSame($decimal, PaymentMoney::toDecimal($money));
    }

    public static function conversionProvider(): array
    {
        return [
            'string' => ['10.50', 'USD', '1050', '10.50'],
            'int' => [12, 'NZD', '1200', '12.00'],
            'float' => [0.1 + 0.2, 'EUR', '30', '0.30'],
            'tiny float' => [0.00001, 'EUR', '0', '0.00'],
            'large float' => [12345678901.23, 'EUR', '1234567890123', '12345678901.23'],
            'negative' => ['-3.5', 'GBP', '-350', '-3.50'],
            'rounded to currency' => ['10.005', 'USD', '1001', '10.01'],
            'no minor unit' => ['1000', 'JPY', '1000', '1000'],
            'three decimals' => ['1.234', 'KWD', '1234', '1.234'],
        ];
    }

    public function testNonIsoCurrencyUsesPrecisionConfig(): void
    {
        $this->assertSame('1.50', PaymentMoney::toDecimal(PaymentMoney::toMoney('1.5', 'XYZ')));

        Config::modify()->set(PaymentMath::class, 'precision', 4);
        $this->assertSame('1.5000', PaymentMoney::toDecimal(PaymentMoney::toMoney('1.5', 'XYZ')));
    }
}
