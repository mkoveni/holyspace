<?php

declare(strict_types=1);

namespace App\People\Infrastructure\ReadModel\DBAL;

use App\People\Application\ReadModel\PeopleReportDirectory;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PeopleReportDirectory::class)]
final readonly class DbalPeopleReportDirectory implements PeopleReportDirectory
{
    public function __construct(private Connection $db)
    {
    }

    public function all(): array
    {
        return array_map(fn ($r) => ['id' => $r['id'], 'name' => trim($r['first_name'].' '.$r['last_name']), 'status' => $r['status'], 'dateOfBirth' => $r['date_of_birth']], $this->db->fetchAllAssociative('SELECT id,first_name,last_name,status,date_of_birth FROM people_persons ORDER BY id'));
    }
}
