<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventMessageContacts;

final readonly class ListEventMessageContactsQuery
{
    public function __construct(public string $registrationId)
    {
    }
}
