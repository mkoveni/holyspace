<?php

declare(strict_types=1);

namespace App\People\Infrastructure\Persistence;

use App\People\Application\ContactIdentity;
use App\People\Domain\ValueObject\ContactKind;
use App\Shared\Infrastructure\Persistence\AdvisoryLock;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Finds who holds a contact. Phone numbers match across spacing and the +27 / 0 prefix; emails match by lower case. */
final readonly class DbalContactIdentity implements ContactIdentity
{
    public function __construct(private Connection $connection, private AdvisoryLock $locks)
    {
    }

    public function claim(ContactKind $kind, string $value): ?string
    {
        $candidates = ContactKind::Phone === $kind ? self::phoneForms($value) : [mb_strtolower($value)];
        $this->locks->lockKey('people.contact:'.$kind->value.':'.$candidates[0]);
        $expression = ContactKind::Phone === $kind ? "regexp_replace(c.value, '[^0-9+]', '', 'g')" : 'lower(c.value)';
        $id = $this->connection->createQueryBuilder()
            ->select('p.id')
            ->from('people_contact_methods', 'c')
            ->innerJoin('c', 'people_persons', 'p', 'p.id = c.person_id')
            ->where('c.kind = :kind')
            ->andWhere('c.ended_at IS NULL')
            ->andWhere('p.archived_at IS NULL')
            ->andWhere($expression.' IN (:values)')
            ->setParameter('kind', $kind->value)
            ->setParameter('values', $candidates, ArrayParameterType::STRING)
            ->orderBy('p.created_at')
            ->addOrderBy('p.id')
            ->setMaxResults(1)
            ->fetchOne();

        return false === $id ? null : (string) $id;
    }

    /**
     * The same number written the ways a South African number is commonly stored; the first form is the lock key.
     *
     * @return non-empty-list<string>
     */
    private static function phoneForms(string $value): array
    {
        $plain = (string) preg_replace('/[^0-9+]/', '', $value);
        $local = match (true) {
            str_starts_with($plain, '+27') => '0'.substr($plain, 3),
            str_starts_with($plain, '0027') => '0'.substr($plain, 4),
            default => $plain,
        };
        $forms = [$local, $plain];
        if (str_starts_with($local, '0') && \strlen($local) > 1) {
            $forms[] = '+27'.substr($local, 1);
        }

        return array_values(array_unique($forms));
    }
}
