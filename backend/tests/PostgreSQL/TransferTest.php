<?php

declare(strict_types=1);

namespace App\Tests\PostgreSQL;

use App\Shared\Infrastructure\Audit\AuditIdentity;
use App\Shared\Infrastructure\Audit\AuditMiddleware;
use App\Shared\Infrastructure\PostgreSQL\PostgresDataTransfer;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use DomainException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use stdClass;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Uid\Uuid;

final class TransferTest extends TestCase
{
    private Connection $sourceAdmin;
    private Connection $targetAdmin;
    private Connection $source;
    private Connection $target;
    private AuditIdentity $identity;
    private string $database;

    protected function setUp(): void
    {
        if (!getenv('MYSQL_TEST_URL') || !getenv('POSTGRES_TEST_URL')) {
            self::markTestSkipped('Set MYSQL_TEST_URL and POSTGRES_TEST_URL to disposable admin databases.');
        }
        $parser = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']);
        $mysql = $parser->parse(getenv('MYSQL_TEST_URL'));
        $postgres = $parser->parse(getenv('POSTGRES_TEST_URL'));
        $this->sourceAdmin = DriverManager::getConnection($mysql);
        $this->targetAdmin = DriverManager::getConnection($postgres);
        $this->database = 'onechurch_transfer_'.bin2hex(random_bytes(6));
        $this->sourceAdmin->executeStatement('CREATE DATABASE '.$this->database);
        $this->targetAdmin->executeStatement('CREATE DATABASE '.$this->database);
        $mysql['dbname'] = $postgres['dbname'] = $this->database;
        $this->source = DriverManager::getConnection($mysql);
        $this->identity = new AuditIdentity(new RequestStack(), new TokenStorage());
        $configuration = new Configuration();
        $configuration->setMiddlewares([new AuditMiddleware($this->identity)]);
        $this->target = DriverManager::getConnection($postgres, $configuration);
        $root = dirname(__DIR__, 2);
        foreach ([[$this->source, $root.'/migrations'], [$this->target, $root.'/migrations-postgresql']] as [$db, $path]) {
            $files = glob($path.'/Version*.php');
            sort($files);
            foreach ($files as $file) {
                // Import into the audited schema before application roles are seeded.
                if (str_contains($path, 'migrations-postgresql') && basename($file) >= 'Version20261021030000.php') {
                    continue;
                }
                require_once $file;
                $class = 'DoctrineMigrations\\'.basename($file, '.php');
                $migration = new $class($db, new NullLogger());
                $migration->up(new Schema());
                foreach ($migration->getSql() as $query) {
                    $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
                }
            }
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) {
            if (isset($this->source)) {
                $this->source->close();
            }
            if (isset($this->target)) {
                $this->target->close();
            }
            $this->sourceAdmin->executeStatement('DROP DATABASE '.$this->database);
            $this->targetAdmin->executeStatement('DROP DATABASE '.$this->database.' WITH (FORCE)');
            $this->sourceAdmin->close();
            $this->targetAdmin->close();
        }
    }

    private function seed(): string
    {
        $id = Uuid::v7()->toRfc4122();
        $this->source->insert('people_persons', ['id' => $id, 'first_name' => 'Transfer', 'last_name' => 'Fixture', 'created_at' => '2026-10-21 08:00:00', 'updated_at' => '2026-10-21 08:00:00']);
        $this->source->insert('giving_records', ['id' => $id, 'kind' => 'funds', 'version' => 1, 'payload' => '{"active":true,"nested":{"z":1,"a":null},"items":[false,10],"emptyObject":{},"emptyList":[]}']);
        $this->source->insert('media_images', ['target' => 'person', 'owner_id' => $id, 'version' => 1, 'content' => "\x00\xffbinary", 'updated_at' => '2026-10-21T08:00:00+00:00'], ['content' => ParameterType::BINARY]);
        $this->source->insert('integration_outbox', ['sequence_no' => 42, 'id' => $id, 'type' => 'fixture', 'schema_version' => 1, 'payload' => '{}', 'created_at' => '2026-10-21T08:00:00+00:00']);

        return $id;
    }

    public function testTransferVerifiesDataAndPreservesTypesAndOutboxOrdering(): void
    {
        $id = $this->seed();
        $transfer = new PostgresDataTransfer($this->target);
        $report = $this->identity->withSource('import', fn () => $transfer->transfer($this->source));
        self::assertSame(1, $report['giving_records']['rows']);
        self::assertSame('Transfer', $this->target->fetchOne('SELECT first_name FROM people_persons WHERE id = ?', [$id]));
        $payload = json_decode($this->target->fetchOne('SELECT payload FROM giving_records WHERE id = ?', [$id]));
        self::assertTrue($payload->active);
        self::assertInstanceOf(stdClass::class, $payload->emptyObject);
        self::assertSame([], $payload->emptyList);
        $content = $this->target->fetchOne('SELECT content FROM media_images WHERE owner_id = ?', [$id]);
        self::assertSame("\x00\xffbinary", is_resource($content) ? stream_get_contents($content) : $content);
        self::assertSame(43, (int) $this->target->fetchOne("SELECT nextval(pg_get_serial_sequence('integration_outbox', 'sequence_no'))"));
        self::assertSame('import', $this->target->fetchOne('SELECT source FROM audit_entries WHERE record_id = ? LIMIT 1', [$id]));
        try {
            $transfer->transfer($this->source);
            self::fail('A populated target must not be overwritten.');
        } catch (DomainException $error) {
            self::assertStringContainsString('fresh', $error->getMessage());
            self::assertSame(1, (int) $this->target->fetchOne('SELECT COUNT(*) FROM giving_records'));
        }
    }

    public function testInvalidPostgresJsonRollsBackTheEntireTransfer(): void
    {
        $this->seed();
        $this->source->executeStatement('UPDATE giving_records SET payload = ?', ['{"invalid":"\u0000"}']);
        $permissions = (int) $this->target->fetchOne('SELECT COUNT(*) FROM permissions');
        try {
            (new PostgresDataTransfer($this->target))->transfer($this->source);
            self::fail('PostgreSQL must reject null characters in JSONB.');
        } catch (\Doctrine\DBAL\Exception) {
            self::assertSame(0, (int) $this->target->fetchOne('SELECT COUNT(*) FROM people_persons'));
            self::assertSame(0, (int) $this->target->fetchOne('SELECT COUNT(*) FROM audit_entries'));
            self::assertSame($permissions, (int) $this->target->fetchOne('SELECT COUNT(*) FROM permissions'));
        }
    }
}
