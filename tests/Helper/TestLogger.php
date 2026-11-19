<?php

declare(strict_types=1);

namespace SilverStripe\Omnipay\Tests\Helper;

use Psr\Log\AbstractLogger;
use SilverStripe\Dev\TestOnly;
use Stringable;

/**
 * Logger that keeps the records in memory, so tests can check what was logged and at which level
 */
class TestLogger extends AbstractLogger implements TestOnly
{
    /**
     * @var list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * The levels that were used for messages that start with the given title (eg. "PurchaseError (Dummy)")
     *
     * @return list<mixed>
     */
    public function levelsFor(string $title): array
    {
        $levels = [];
        foreach ($this->records as $record) {
            if ($record['message'] === $title) {
                $levels[] = $record['level'];
            }
        }
        return $levels;
    }
}
