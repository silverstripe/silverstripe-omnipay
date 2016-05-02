<?php

namespace SilverStripe\Omnipay;

/**
 * Helper class to deal with payment arithmetic.
 *
 * Note of advice: If PHP wasn't compiled with BC Math (http://php.net/manual/en/book.bc.php), you can run into
 * number-overflow issues quickly when dealing with high precision and/or multiplication of large numbers.
 */
class PaymentMath
{
    /**
     * Desired precision for the output strings.
     *
     * @config Can be configured via `SilverStripe\Omnipay\PaymentMath.precision`
     * @var int
     */
    private static $precision = 2;

    /**
     * Whether or not to use bc-math functions. Should be set to true, if possible.
     * Only set this to false for unit-tests!
     *
     * @config Can be configured via `SilverStripe\Omnipay\PaymentMath.useBcMath`
     * @var bool
     */
    private static $useBcMath = true;

    /**
     * Subtract two numbers that are represented as a string.
     * Numbers will not be rounded but floored instead! So 10.0 - 0.1 with a precision of 0 will result in 9!
     * @param string $amountA first operand
     * @param string $amountB second operand
     * @return string the result as a string
     */
    public static function subtract($amountA, $amountB)
    {
        $precision = (int)\Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'precision');
        if (function_exists('bcsub') && \Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'useBcMath')) {
            return bcsub($amountA, $amountB, $precision);
        }

        return self::formatFloat((double)$amountA - (double)$amountB, $precision);
    }

    /**
     * Add two numbers that are represented as a string
     * Numbers will not be rounded but floored instead! So 0.22 + 0.27 with a precision of 1 will result in 0.4!
     * @param string $amountA first operand
     * @param string $amountB second operand
     * @return string the result as a string
     */
    public static function add($amountA, $amountB)
    {
        $precision = (int)\Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'precision');
        if (function_exists('bcadd') && \Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'useBcMath')) {
            return bcadd($amountA, $amountB, $precision);
        }

        return self::formatFloat((double)$amountA + (double)$amountB, $precision);
    }

    /**
     * Multiply two numbers that are represented as a string
     * Numbers will not be rounded but floored instead! So 0.001 * 10 with a precision of 1 will result in 0!
     * @param string $amountA first operand
     * @param string $amountB second operand
     * @return string the result as a string
     */
    public static function multiply($amountA, $amountB)
    {
        $precision = (int)\Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'precision');
        if (function_exists('bcmul') && \Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'useBcMath')) {
            return bcmul($amountA, $amountB, $precision);
        }

        return self::formatFloat((double)$amountA * (double)$amountB, $precision);
    }

    /**
     * Compare two numbers that are represented as a string
     * @param string $amountA first operand
     * @param string $amountB second operand
     * @return int 0 when both numbers are equal, 1 when $amountA is bigger than $amountB, -1 otherwise
     */
    public static function compare($amountA, $amountB)
    {
        $precision = (int)\Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'precision');
        if (function_exists('bccomp') && \Config::inst()->get('SilverStripe\Omnipay\PaymentMath', 'useBcMath')) {
            return bccomp($amountA, $amountB, $precision);
        }

        $scale = pow(10, max(0, $precision));
        $a = (int)($scale * $amountA);
        $b = (int)($scale * $amountB);

        return max(-1, min(1, $a - $b));
    }

    /**
     * Format a float to string
     * @param float $f the number to format as string
     * @param int $precision desired precision
     * @return string
     */
    private static function formatFloat($f, $precision)
    {
        $scale = pow(10, max(0, $precision));
        // clear off additional digits so that number_format doesn't round numbers
        $i = (int)($f * $scale) / $scale;
        return number_format($i, $precision, '.', '');
    }
}
