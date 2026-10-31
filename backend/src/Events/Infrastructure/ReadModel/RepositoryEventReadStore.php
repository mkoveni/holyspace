<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\ReadModel;

use App\Events\Application\ReadModel\EventReadStore;
use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\ValueObject\EventId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(EventReadStore::class)]
final readonly class RepositoryEventReadStore implements EventReadStore
{
    public function __construct(private EventRepository $records)
    {
    }

    public function find(EventId $id): ?ChurchEvent
    {
        return $this->records->find($id);
    }

    /** @return list<ChurchEvent> */
    public function all(): array
    {
        return $this->records->all();
    }
}
