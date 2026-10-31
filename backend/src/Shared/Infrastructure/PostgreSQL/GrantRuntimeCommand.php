<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\PostgreSQL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:database:grant-runtime', description: 'Grant an existing non-owner PostgreSQL runtime role business access and read-only audit access.')]
final class GrantRuntimeCommand extends Command
{
    public function __construct(private readonly Connection $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('role', InputArgument::REQUIRED, 'Existing runtime role, distinct from the migration/database owner.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $role = (string) $input->getArgument('role');
        if (!$this->db->getDatabasePlatform() instanceof PostgreSQLPlatform || !preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $role)) {
            $output->writeln('<error>A PostgreSQL connection and valid role name are required.</error>');

            return Command::FAILURE;
        }
        $roleInfo = $this->db->fetchAssociative("SELECT rolsuper OR rolcreaterole OR rolcreatedb OR rolreplication OR rolbypassrls OR pg_has_role(oid, (SELECT datdba FROM pg_database WHERE datname = current_database()), 'MEMBER') AS unsafe FROM pg_roles WHERE rolname = ?", [$role]);
        if (false === $roleInfo || in_array($roleInfo['unsafe'], [true, 't', 1], true)) {
            $output->writeln('<error>Use an existing unprivileged runtime role, separate from the database owner.</error>');

            return Command::FAILURE;
        }
        $quoted = $this->db->quoteIdentifier($role);
        $this->db->transactional(function () use ($quoted) {
            $this->db->executeStatement('GRANT USAGE ON SCHEMA public TO '.$quoted);
            foreach ($this->db->createSchemaManager()->listTableNames() as $table) {
                $name = $this->db->quoteIdentifier($table);
                $this->db->executeStatement('REVOKE ALL ON TABLE '.$name.' FROM '.$quoted);
                $privileges = match ($table) {
                    'audit_entries', 'doctrine_migration_versions' => 'SELECT',
                    'workflow_audit', 'account_security_audit' => 'SELECT, INSERT',
                    default => 'SELECT, INSERT, UPDATE, DELETE',
                };
                $this->db->executeStatement('GRANT '.$privileges.' ON TABLE '.$name.' TO '.$quoted);
            }
            $this->db->executeStatement('GRANT USAGE, SELECT ON SEQUENCE integration_outbox_sequence_no_seq TO '.$quoted);
            $this->db->executeStatement('REVOKE ALL ON SEQUENCE audit_entries_id_seq FROM '.$quoted);
        });
        $output->writeln('<info>Runtime privileges applied. Keep migration credentials out of web and worker environments.</info>');

        return Command::SUCCESS;
    }
}
