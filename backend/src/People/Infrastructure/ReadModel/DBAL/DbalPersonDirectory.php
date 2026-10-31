<?php

declare(strict_types=1);

namespace App\People\Infrastructure\ReadModel\DBAL;

use App\People\Application\ReadModel\PersonDirectory;
use App\Shared\Domain\ValueObject\Input;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PersonDirectory::class)]
final readonly class DbalPersonDirectory implements PersonDirectory
{
    public function __construct(private Connection $db)
    {
    }

    public function get(string $id): ?array
    {
        return $this->getMany([$id])[$id] ?? null;
    }

    public function getMany(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        foreach ($ids as $id) {
            Input::id($id);
        }
        $people = [];
        // Bound parameter counts while avoiding a query per person.
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = $this->db->fetchAllAssociative(
                'SELECT id, first_name, last_name, date_of_birth, email, phone FROM people_persons WHERE id IN (?)',
                [$chunk], [ArrayParameterType::STRING],
            );
            foreach ($rows as $row) {
                $people[$row['id']] = ['id' => $row['id'], 'name' => $row['first_name'].' '.$row['last_name'], 'dateOfBirth' => $row['date_of_birth'], 'email' => $row['email'], 'phone' => $row['phone']];
            }
        }

        return $people;
    }
}
