<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Model;

use Omnipay\Common\Message\RequestInterface;
use SilverStripe\Dev\TestOnly;
use Omnipay\Common\AbstractGateway;

/**
 * @method RequestInterface authorize(array $options = [])
 * @method RequestInterface completeAuthorize(array $options = [])
 * @method RequestInterface capture(array $options = [])
 * @method RequestInterface completePurchase(array $options = [])
 * @method RequestInterface refund(array $options = [])
 * @method RequestInterface void(array $options = [])
 * @method RequestInterface createCard(array $options = [])
 * @method RequestInterface updateCard(array $options = [])
 * @method RequestInterface deleteCard(array $options = [])
 */
class TestOnsiteGateway extends AbstractGateway implements TestOnly
{
    public function getName()
    {
        return 'TestOnsite';
    }

    public function getDefaultParameters()
    {
        return [];
    }

    public function purchase(array $parameters = [])
    {
    }

    public function __call(string $name, array $arguments)
    {
    }
}
