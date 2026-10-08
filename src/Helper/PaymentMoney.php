<?php

namespace SilverStripe\Omnipay\Helper;

use Money\Currencies;
use Money\Currencies\CurrencyList;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

/**
 * Converts between the decimal amounts stored on payments and {@link Money} objects, which are used for all
 * payment arithmetic. Amounts are rounded to the number of decimals of the currency (eg. 2 for USD, 0 for JPY).
 *
 * Currencies that aren't part of ISO 4217 use the {@link PaymentMath} `precision` config.
 */
class PaymentMoney
{
    /**
     * @param string|int|float $amount decimal amount, eg. "10.50"
     * @param string $currency currency code, eg. "NZD"
     */
    public static function toMoney(string|int|float $amount, string $currency): Money
    {
        if (is_float($amount)) {
            // avoid scientific notation when casting floats to string
            $amount = rtrim(rtrim(number_format($amount, 12, '.', ''), '0'), '.');
        }

        $parser = new DecimalMoneyParser(self::currencies($currency));
        return $parser->parse(trim((string) $amount), new Currency($currency));
    }

    /**
     * @return string decimal amount, eg. "10.50"
     */
    public static function toDecimal(Money $money): string
    {
        $formatter = new DecimalMoneyFormatter(self::currencies($money->getCurrency()->getCode()));
        return $formatter->format($money);
    }

    private static function currencies(string $currency): Currencies
    {
        $iso = new ISOCurrencies();
        if ($currency !== '' && $iso->contains(new Currency($currency))) {
            return $iso;
        }

        return new CurrencyList([$currency => max(0, (int) PaymentMath::config()->get('precision'))]);
    }
}
