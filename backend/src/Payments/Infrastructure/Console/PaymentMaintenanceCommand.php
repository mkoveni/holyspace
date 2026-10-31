<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Console;

use App\Payments\Application\Command\MaintainPayments\MaintainPaymentsCommand;
use App\Payments\Application\Command\MaintainPayments\MaintainPaymentsHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:payments:maintain', description: 'Expire hosted payment reservations and flag cancelled paid tickets for refund review.')]
final class PaymentMaintenanceCommand extends Command
{
    public function __construct(private readonly MaintainPaymentsHandler $maintainPaymentsHandler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ($this->maintainPaymentsHandler)(new MaintainPaymentsCommand());
        $output->writeln('Payment reservations checked.');

        return Command::SUCCESS;
    }
}
