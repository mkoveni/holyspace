<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\ReadModel;

use App\Events\Application\ReadModel\EventRegistrationReadStore;
use App\Events\Domain\Model\EventRegistration;
use App\Events\Domain\Model\RegistrationSettings;
use App\Events\Domain\Repository\EventRegistrationRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(EventRegistrationReadStore::class)]
final readonly class RepositoryEventRegistrationReadStore implements EventRegistrationReadStore
{
    public function __construct(private EventRegistrationRepository $records)
    {
    }

    public function settings(string $occurrenceId): RegistrationSettings
    {
        return $this->records->settings($occurrenceId);
    }

    public function find(string $id): ?EventRegistration
    {
        return $this->records->find($id);
    }

    public function forPerson(string $occurrenceId, string $personId): ?EventRegistration
    {
        return $this->records->forPerson($occurrenceId, $personId);
    }

    public function pendingPayments(string $occurrenceId): array
    {
        return $this->records->pendingPayments($occurrenceId);
    }

    public function counts(string $occurrenceId): array
    {
        return $this->records->counts($occurrenceId);
    }

    public function page(string $occurrenceId, ?string $after, int $limit): array
    {
        return $this->records->page($occurrenceId, $after, $limit);
    }

    public function nextWaitlisted(string $occurrenceId): ?EventRegistration
    {
        return $this->records->nextWaitlisted($occurrenceId);
    }
}
