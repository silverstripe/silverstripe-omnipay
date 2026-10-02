<?php

namespace SilverStripe\Omnipay\Tests\Service;

use Exception;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Security\RandomGenerator;

/**
 * Class TestRandomGenerator
 * @package SilverStripe\Omnipay\Tests\Service
 */
class TestRandomGenerator extends RandomGenerator implements TestOnly
{
    protected array $entropy = [];

    protected array $randomToken = [];

    public function addEntropy(string ...$values): void
    {
        $this->entropy = array_merge($this->entropy, $values);
    }

    public function addRandomTokens(string ...$tokens): void
    {
        $this->randomToken = array_merge($this->randomToken, $tokens);
    }

    /**
     * @throws Exception
     */
    public function generateEntropy(): ?string
    {
        if ($this->entropy !== []) {
            return array_shift($this->entropy);
        }

        return bin2hex(random_bytes(32));
    }

    /**
     * @param string $algorithm
     */
    public function randomToken($algorithm = 'whirlpool'): array|string|null
    {
        if ($this->randomToken !== []) {
            return array_shift($this->randomToken);
        }

        return parent::randomToken($algorithm);
    }
}
