<?php

declare(strict_types=1);

namespace App\Forms\Application\Query\GetPublicFormPage;

use App\Events\Application\ReadModel\PublicEventDetails;
use App\Forms\Application\Query\GetPublicForm\GetPublicFormHandler;
use App\Forms\Application\Query\GetPublicForm\GetPublicFormQuery;

final readonly class GetPublicFormPageHandler
{
    public function __construct(private GetPublicFormHandler $forms, private PublicEventDetails $events)
    {
    }

    public function __invoke(GetPublicFormPageQuery $query): array
    {
        $form = ($this->forms)(new GetPublicFormQuery($query->token));

        return ['title' => $form->title, 'description' => $form->description, 'fields' => $form->fields, 'version' => $form->version, 'isEventRegistration' => null !== $form->eventId, 'event' => null !== $form->eventId ? $this->events->get($form->eventId, $form->occurrenceId) : null];
    }
}
