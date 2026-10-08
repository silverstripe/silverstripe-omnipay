<?php

namespace SilverStripe\Omnipay\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Omnipay\Migration\PaymentMessageMigrator;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Upgrades payment messages written by omnipay 5.x or older to the single-table message model.
 *
 * The same migration runs automatically on `dev/build` (see
 * {@link \SilverStripe\Omnipay\Model\Message\PaymentMessage::$migrate_legacy_messages_on_build}). Use this task to
 * preview the changes with `--dry-run`, or to run the migration manually when the automatic one is disabled.
 *
 * Safe to run multiple times.
 */
class MigratePaymentMessageTypesTask extends BuildTask
{
    protected static string $commandName = 'MigratePaymentMessageTypesTask';

    protected string $title = 'Migrate legacy payment messages';

    protected static string $description = 'Upgrades payment messages from omnipay 5.x or older to the single-table model';

    public function getOptions(): array
    {
        return [
            new InputOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only report what would be migrated, without changing the database'
            ),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $results = PaymentMessageMigrator::create()->migrate($dryRun);

        if (!$results) {
            $output->writeln('Nothing to migrate.');
            return Command::SUCCESS;
        }

        foreach ($results as $step => $count) {
            $output->writeln(sprintf('%s%s: %d', $dryRun ? '[dry run] ' : '', $step, $count));
        }

        return Command::SUCCESS;
    }
}
