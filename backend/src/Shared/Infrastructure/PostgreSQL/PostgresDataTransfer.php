<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\PostgreSQL;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\JsonType;
use DomainException;
use stdClass;

/** One-shot transfer into a fresh PostgreSQL baseline. Call only during maintenance. */
final readonly class PostgresDataTransfer
{
    public function __construct(private Connection $target)
    {
    }

    public function transfer(Connection $source): array
    {
        if (!$this->target->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            throw new DomainException('The target must be PostgreSQL.');
        }
        $schema = $this->target->createSchemaManager()->introspectSchema();
        $tables = [];
        foreach ($schema->getTables() as $table) {
            if (!in_array($table->getName(), ['audit_entries', 'doctrine_migration_versions', 'lock_keys'], true)) {
                $tables[$table->getName()] = $table;
            }
        }
        // Topological ordering respects existing foreign keys without disabling constraints.
        $ordered = [];
        while ([] !== $tables) {
            $progress = false;
            foreach ($tables as $name => $table) {
                $dependencies = array_map(static fn ($fk) => $fk->getForeignTableName(), $table->getForeignKeys());
                if ([] !== array_intersect($dependencies, array_keys($tables))) {
                    continue;
                }
                $ordered[$name] = $table;
                unset($tables[$name]);
                $progress = true;
            }
            if (!$progress) {
                throw new DomainException('Schema contains a dependency cycle; transfer stopped.');
            }
        }
        $source->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $source->executeStatement('SET SESSION TRANSACTION READ ONLY');
        $source->beginTransaction();
        try {
            return $this->target->transactional(function () use ($source, $ordered): array {
                $this->target->executeStatement("SET LOCAL TIME ZONE 'UTC'");
                $names = array_map($this->target->quoteIdentifier(...), array_keys($ordered));
                $this->target->executeStatement('LOCK TABLE '.implode(', ', $names).' IN ACCESS EXCLUSIVE MODE');
                if (0 !== (int) $this->target->fetchOne('SELECT COUNT(*) FROM audit_entries')) {
                    throw new DomainException('Target has audit history. Use a fresh migrated database.');
                }
                foreach ($ordered as $name => $_) {
                    if ('permissions' !== $name && 0 !== (int) $this->target->fetchOne('SELECT COUNT(*) FROM '.$this->target->quoteIdentifier($name))) {
                        throw new DomainException('Target contains business records. Transfer refused.');
                    }
                }
                // Baseline permission IDs are replaced by the source IDs used in role assignments.
                $this->target->executeStatement('DELETE FROM permissions');
                $report = [];
                foreach ($ordered as $name => $table) {
                    $quoted = $this->target->quoteIdentifier($name);
                    $keys = $table->getPrimaryKey()->getColumns();
                    $order = implode(', ', array_map($this->target->quoteIdentifier(...), $keys));
                    $columns = [];
                    foreach ($table->getColumns() as $column) {
                        $columns[$column->getName()] = $column;
                    }
                    $sourceDigest = hash_init('sha256');
                    $count = 0;
                    foreach ($source->iterateAssociative('SELECT * FROM '.$source->quoteIdentifier($name).' ORDER BY '.implode(', ', array_map($source->quoteIdentifier(...), $keys))) as $row) {
                        if (isset($columns['payload_schema_version']) && !array_key_exists('payload_schema_version', $row)) {
                            $row['payload_schema_version'] = 1;
                        }
                        $types = [];
                        foreach ($row as $column => &$value) {
                            if ($columns[$column]->getType() instanceof BlobType && null !== $value) {
                                if (is_resource($value)) {
                                    $value = stream_get_contents($value);
                                }
                                $types[$column] = ParameterType::BINARY;
                            }
                        }
                        unset($value);
                        $this->target->insert($name, $row, $types);
                        hash_update($sourceDigest, $this->canonical($row, $columns)."\n");
                        ++$count;
                    }
                    $targetDigest = hash_init('sha256');
                    $targetCount = 0;
                    foreach ($this->target->iterateAssociative('SELECT * FROM '.$quoted.' ORDER BY '.$order) as $row) {
                        hash_update($targetDigest, $this->canonical($row, $columns)."\n");
                        ++$targetCount;
                    }
                    $checksum = hash_final($sourceDigest);
                    if ($count !== $targetCount || !hash_equals($checksum, hash_final($targetDigest))) {
                        throw new DomainException('Transferred data failed checksum verification for '.$name.'.');
                    }
                    $report[$name] = ['rows' => $count, 'checksum' => $checksum];
                }
                $this->target->executeStatement("SELECT setval(pg_get_serial_sequence('integration_outbox', 'sequence_no'), COALESCE(MAX(sequence_no), 1), COUNT(*) > 0) FROM integration_outbox");

                return $report;
            });
        } finally {
            $source->rollBack();
        }
    }

    private function canonical(array $row, array $columns): string
    {
        foreach ($row as $key => &$value) {
            if (null === $value) {
                continue;
            }
            if (is_resource($value)) {
                $value = stream_get_contents($value);
            }
            if ($columns[$key]->getType() instanceof JsonType) {
                $value = $this->sortJson(json_decode($value, false, flags: JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING));
            } elseif ($columns[$key]->getType() instanceof BlobType) {
                $value = base64_encode($value);
            } elseif (in_array($key, ['created_at', 'updated_at', 'occurred_at', 'processed_at', 'starts_at', 'ends_at', 'ended_at'], true)) {
                $value = (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
            } else {
                $value = (string) $value;
            }
        }
        unset($value);
        ksort($row);

        return json_encode($row, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function sortJson(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties);

            return (object) array_map($this->sortJson(...), $properties);
        }
        if (is_array($value)) {
            return array_map($this->sortJson(...), $value);
        }

        return $value;
    }
}
