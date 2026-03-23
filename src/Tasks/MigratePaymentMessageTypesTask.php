<?php

namespace SilverStripe\Omnipay\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Omnipay\Model\Message\PaymentMessage;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * One-off upgrade: populate {@link PaymentMessage::Type} from legacy subclass ClassName values.
 *
 * Run once after upgrading from versions that used one DataObject subclass per message kind.
 * Safe to run multiple times (skips rows that already have Type set).
 */
class MigratePaymentMessageTypesTask extends BuildTask
{
    private static string $segment = 'MigratePaymentMessageTypesTask';

    protected string $title = 'Migrate payment message types';

    protected static string $description = 'Fills the Type column on payment messages from legacy ClassName values';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $count = 0;
        /** @var PaymentMessage $message */
        foreach (PaymentMessage::get() as $message) {
            if ($message->Type) {
                continue;
            }
            $class = $message->ClassName;
            if (!$class) {
                continue;
            }
            if ($class === PaymentMessage::class
                || $class === 'SilverStripe\\Omnipay\\Model\\Message\\PaymentRequestMessage'
            ) {
                continue;
            }
            $pos = strrpos($class, '\\');
            $shortName = $pos === false ? $class : substr($class, $pos + 1);
            if ($shortName === '') {
                $shortName = $class;
            }
            $message->Type = $shortName;
            $message->write();
            $count++;
        }
        $output->writeln("Updated {$count} payment message(s).");

        return Command::SUCCESS;
    }
}
