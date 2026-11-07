<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence;

use App\People\Application\Contract\Title;
use App\People\Application\Contract\TitleReader;
use App\People\Application\Contract\TitleWriter;
use Doctrine\DBAL\Connection;

final readonly class DbalTitleStore implements TitleReader, TitleWriter
{
    public function __construct(private Connection $connection)
    {
    }

    public function titles(): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('code', 'label')
            ->from('people_titles')
            ->orderBy('label')
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): Title => new Title((string) $row['code'], (string) $row['label']), $rows));
    }

    public function exists(string $code): bool
    {
        return false !== $this->connection->createQueryBuilder()
            ->select('1')
            ->from('people_titles')
            ->where('code = :code')
            ->setParameter('code', $code)
            ->fetchOne();
    }

    public function add(string $code, string $label): void
    {
        $this->connection->insert('people_titles', ['code' => $code, 'label' => $label]);
    }
}
