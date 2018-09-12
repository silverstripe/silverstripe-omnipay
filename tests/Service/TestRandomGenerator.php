<?php

namespace SilverStripe\Omnipay\Tests\Service;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Security\RandomGenerator;

/**
 * Class TestRandomGenerator
 * @package SilverStripe\Omnipay\Tests\Service
 */
class TestRandomGenerator extends RandomGenerator implements TestOnly
{
    /**
     * @var array
     */
    protected $entropy = [];

    /**
     * @var array
     */
    protected $randomToken = [];

    /**
     * @param string ...$values
     */
    public function addEntropy(...$values)
    {
        $this->entropy = array_merge($this->entropy, $values);
    }

    /**
     * @param string ...$tokens
     */
    public function addRandomTokens(...$tokens)
    {
        $this->randomToken = array_merge($this->randomToken, $tokens);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function generateEntropy()
    {
        if (!empty($this->entropy)) {
            return array_shift($this->entropy);
        }

        return parent::generateEntropy();
    }

    /**
     * @param string $algorithm
     * @return string
     */
    public function randomToken($algorithm = 'whirlpool')
    {
        if (!empty($this->randomToken)) {
            return array_shift($this->randomToken);
        }

        return parent::randomToken($algorithm);
    }
}
