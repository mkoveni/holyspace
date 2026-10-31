<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\PostgreSQL;

use App\Shared\Infrastructure\Audit\AuditIdentity;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use DomainException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(name: 'app:database:import-mysql', description: 'Copy and verify MySQL data into a fresh migrated PostgreSQL database. Requires maintenance mode and MYSQL_SOURCE_URL.')]
final class ImportMysqlCommand extends Command
{
    public function __construct(private readonly PostgresDataTransfer $transfer, private readonly AuditIdentity $identity)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = getenv('MYSQL_SOURCE_URL');
        if (!$url) {
            $output->writeln('<error>Set MYSQL_SOURCE_URL to a read-only MySQL source.</error>');

            return Command::FAILURE;
        }
        $source = null;
        try {
            $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($url);
            if ('pdo_mysql' !== $params['driver']) {
                throw new DomainException('Source must be MySQL.');
            }
            $source = DriverManager::getConnection($params);
            $report = $this->identity->withSource('import', fn () => $this->transfer->transfer($source));
            $output->writeln(json_encode(['verified' => true, 'tables' => $report], JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        } catch (Throwable $error) {
            // Never print source credentials, SQL parameters, or private data from exceptions.
            $output->writeln('<error>Transfer failed and target writes were rolled back. Confirm a fresh target, matching schemas, valid data, and maintenance mode. Error type: '.$error::class.'</error>');

            return Command::FAILURE;
        } finally {
            $source?->close();
        }
    }
}
