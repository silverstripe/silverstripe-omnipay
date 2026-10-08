<?php

namespace SilverStripe\Omnipay\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Removes payments that were abandoned before completion, eg. when a customer never returned from an offsite gateway.
 *
 * Only payments in one of the configured `statuses` that weren't modified for `max_age_days` are considered.
 * Payments with a transaction reference are skipped, since the gateway might know about them.
 *
 * The task only reports matching payments, unless it's run with the `--delete` option.
 */
class CleanupAbandonedPaymentsTask extends BuildTask
{
    private static string $segment = 'CleanupAbandonedPaymentsTask';

    protected string $title = 'Clean up abandoned payments';

    protected static string $description = 'Removes payments (and their messages) that were never completed';

    /**
     * Payment statuses that are considered abandoned once they reach the max age.
     *
     * Pending captures, refunds and voids aren't included by default, as these are created by the merchant and
     * usually wait for a notification from the gateway.
     *
     * @config
     * @var list<string>
     */
    private static array $statuses = [
        'Created',
        'PendingAuthorization',
        'PendingPurchase',
        'PendingCreateCard',
    ];

    /**
     * Number of days since a payment was last modified, before it's considered abandoned.
     *
     * @config
     */
    private static int $max_age_days = 3;

    public function getOptions(): array
    {
        return [
            new InputOption(
                'delete',
                null,
                InputOption::VALUE_NONE,
                'Delete abandoned payments. Without this option, payments are only listed'
            ),
            new InputOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Only remove payments older than this number of days (defaults to the max_age_days config)'
            ),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $days = $input->getOption('days') ?? static::config()->get('max_age_days');
        if (!is_numeric($days) || (int) $days < 1) {
            $output->writeln('<error>The number of days must be a positive number.</>');
            return Command::INVALID;
        }

        $delete = (bool) $input->getOption('delete');
        $payments = $this->getAbandonedPayments((int) $days);

        $count = 0;
        /** @var Payment $payment */
        foreach ($payments as $payment) {
            $output->writeln(sprintf(
                '%s payment #%d (%s, %s %s, last modified %s)',
                $delete ? 'Deleting' : 'Found',
                $payment->ID,
                $payment->Status,
                $payment->MoneyAmount,
                $payment->MoneyCurrency,
                $payment->LastEdited
            ));

            if ($delete) {
                foreach ($payment->Messages() as $message) {
                    $message->delete();
                }
                $payment->delete();
            }
            $count++;
        }

        $output->writeln($delete
            ? "Deleted {$count} abandoned payment(s)."
            : "Found {$count} abandoned payment(s). Run with --delete to remove them.");

        return Command::SUCCESS;
    }

    /**
     * @return DataList<Payment>
     */
    public function getAbandonedPayments(int $days): DataList
    {
        $cutoff = date('Y-m-d H:i:s', DBDatetime::now()->getTimestamp() - $days * 86400);

        return Payment::get()->filter([
            'Status' => static::config()->get('statuses'),
            'LastEdited:LessThan' => $cutoff,
            'TransactionReference' => [null, ''],
        ]);
    }
}
