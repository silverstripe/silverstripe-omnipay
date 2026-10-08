<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Model;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Omnipay\Model\Message\PaymentMessage;

class PaymentMessageTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testOverlongValuesAreTruncated(): void
    {
        $message = PaymentMessage::create([
            'Message' => str_repeat('x', 1000),
            'Code' => str_repeat('9', 1000),
            'Type' => 'PurchaseError',
        ]);
        $message->write();

        $message = PaymentMessage::get()->byID($message->ID);
        $this->assertSame(255, mb_strlen($message->Message));
        $this->assertSame(255, mb_strlen($message->Code));
    }

    public function testCmsCannotModifyMessages(): void
    {
        $this->logInWithPermission('ADMIN');
        $message = PaymentMessage::create();
        $this->assertFalse($message->canCreate());
        $this->assertFalse($message->canEdit());
        $this->assertFalse($message->canDelete());
    }
}
