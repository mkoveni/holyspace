<?php

declare(strict_types=1);

namespace App\Media\Infrastructure;

use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\ValueObject\EventId;
use App\Forms\Application\Query\GetForm\GetFormHandler;
use App\Forms\Application\Query\GetForm\GetFormQuery;
use App\Forms\Application\Query\GetPublicForm\GetPublicFormHandler;
use App\Forms\Application\Query\GetPublicForm\GetPublicFormQuery;
use App\Media\Application\ImageTargets;
use App\People\Application\ReadModel\PersonDirectory;
use App\People\Domain\Repository\FamilyRepository;
use App\People\Domain\ValueObject\FamilyId;
use OutOfBoundsException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ImageTargets::class)]
final readonly class PortalImageTargets implements ImageTargets
{
    public function __construct(
        private PersonDirectory $people,
        private FamilyRepository $families,
        private EventRepository $events,
        private GetFormHandler $getFormHandler,
        private GetPublicFormHandler $getPublicFormHandler,
    ) {
    }

    public function require(string $target, string $id): void
    {
        $exists = match ($target) {
            'person' => $this->people->get($id),
            'family' => $this->families->findById(FamilyId::fromString($id)),
            'event' => $this->events->find(EventId::fromString($id)),
            'form' => ($this->getFormHandler)(new GetFormQuery($id)),
            default => null,
        };
        if (!$exists) {
            throw new OutOfBoundsException('Image owner not found.');
        }
    }

    public function publishedEventId(string $token): string
    {
        $form = ($this->getPublicFormHandler)(new GetPublicFormQuery($token));

        return $form->eventId ?? throw new OutOfBoundsException('Event image unavailable.');
    }

    public function publishedFormId(string $token): string
    {
        return ($this->getPublicFormHandler)(new GetPublicFormQuery($token))->id;
    }
}
