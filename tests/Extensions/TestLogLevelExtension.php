<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Changes the log level of one message type, to test the `updateLogLevel` hook
 * @extends Extension<static>
 */
class TestLogLevelExtension extends Extension implements TestOnly
{
    public static ?string $type = null;

    public static mixed $level = null;

    /**
     * @var list<array{level: ?string, type: string, data: mixed}>
     */
    public static array $calls = [];

    public static function reset(): void
    {
        static::$type = null;
        static::$level = null;
        static::$calls = [];
    }

    public function updateLogLevel(?string &$level, string $type, mixed $data): void
    {
        static::$calls[] = ['level' => $level, 'type' => $type, 'data' => $data];
        if ($type === static::$type) {
            $level = static::$level;
        }
    }
}
