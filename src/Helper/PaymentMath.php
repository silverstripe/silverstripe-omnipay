<?php

namespace SilverStripe\Omnipay\Helper;

use SilverStripe\Core\Config\Configurable;

/**
 * Helper class to deal with payment arithmetic.
 *
 * Note of advice: If PHP wasn't compiled with BC Math (http://php.net/manual/en/book.bc.php), you can run into
 * number-overflow issues quickly when dealing with high precision and/or multiplication of large numbers.
 */
class PaymentMath
{
    use Configurable;

    /**
     * Desired precision for the output strings.
     *
     * @config Can be configured via `SilverStripe\Omnipay\Helper\PaymentMath.precision`
     * @var int
     */
    private static $precision = 2;

    /**
     * Whether or not to use bc-math functions. Should be set to true, if possible.
     * Only set this to false for unit-tests!
     *
     * @config Can be configured via `SilverStripe\Omnipay\Helper\PaymentMath.useBcMath`
     * @var bool
     */
    private static $useBcMath = true;

    /**
     * Subtract two numbers that are represented as a string.
     * Numbers will not be rounded but floored instead! So 10.0 - 0.1 with a precision of 0 will result in 9!
     * @param string|float|int $amountA first operand
     * @param string|float|int $amountB second operand
     * @return string the result as a string
     */
    public static function subtract($amountA, $amountB)
    {
        $precision = (int)self::config()->get('precision');
        if (function_exists('bcsub') && self::config()->get('useBcMath')) {
            return bcsub((string) $amountA, (string) $amountB, $precision);
        }

        return self::formatFloat((float) $amountA - (float) $amountB, $precision);
    }

    /**
     * Add two numbers that are represented as a string
     * Numbers will not be rounded but floored instead! So 0.22 + 0.27 with a precision of 1 will result in 0.4!
     * @param string|float|int $amountA first operand
     * @param string|float|int $amountB second operand
     * @return string the result as a string
     */
    public static function add($amountA, $amountB)
    {
        $precision = (int) self::config()->get('precision');
        if (function_exists('bcadd') && self::config()->get('useBcMath')) {
            return bcadd((string) $amountA, (string) $amountB, $precision);
        }

        return self::formatFloat((float) $amountA + (float) $amountB, $precision);
    }

    /**
     * Multiply two numbers that are represented as a string
     * Numbers will not be rounded but floored instead! So 0.001 * 10 with a precision of 1 will result in 0!
     * @param string|float|int $amountA first operand
     * @param string|float|int $amountB second operand
     * @return string the result as a string
     */
    public static function multiply($amountA, $amountB)
    {
        $precision = (int) self::config()->get('precision');
        if (function_exists('bcmul') && self::config()->get('useBcMath')) {
            return bcmul((string) $amountA, (string) $amountB, $precision);
        }

        return self::formatFloat((float) $amountA * (float) $amountB, $precision);
    }

    /**
     * Compare two numbers that are represented as a string
     * @param string|float|int $amountA first operand
     * @param string|float|int $amountB second operand
     * @return int 0 when both numbers are equal, 1 when $amountA is bigger than $amountB, -1 otherwise
     */
    public static function compare($amountA, $amountB)
    {
        $precision = (int) self::config()->get('precision');
        if (function_exists('bccomp') && self::config()->get('useBcMath')) {
            return bccomp((string) $amountA, (string) $amountB, $precision);
        }

        $scale = pow(10, max(0, $precision));
        $scaledA = $scale * (float) $amountA;
        $scaledB = $scale * (float) $amountB;

        if (abs($scaledA) <= PHP_INT_MAX && abs($scaledB) <= PHP_INT_MAX) {
            $a = (int) $scaledA;
            $b = (int) $scaledB;

            return max(-1, min(1, $a - $b));
        }

        if (function_exists('bccomp')) {
            return bccomp((string) $amountA, (string) $amountB, $precision);
        }

        return max(-1, min(1, $scaledA <=> $scaledB));
    }

    /**
     * Format a float to string
     * @param float $f the number to format as string
     * @param int $precision desired precision
     * @return string
     */
    private static function formatFloat($f, $precision)
    {
        $precision = max(0, (int) $precision);
        $scale = pow(10, $precision);
        $scaled = $f * $scale;

        if (!is_finite($scaled)) {
            return number_format($f, $precision, '.', '');
        }

        // Avoid (int) cast when the scaled value is outside int range — PHP 8.4+ warns and truncates incorrectly.
        if (abs($scaled) <= PHP_INT_MAX) {
            $i = (int) $scaled / $scale;
        } else {
            $i = ($scaled >= 0 ? floor($scaled) : ceil($scaled)) / $scale;
        }

        // PHP 8+ interprets negative decimals in number_format as rounding to tens etc.; use non-negative decimals only
        return number_format($i, $precision, '.', '');
    }
}
