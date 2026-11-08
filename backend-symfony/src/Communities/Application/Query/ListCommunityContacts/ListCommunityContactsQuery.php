<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityContacts;

final readonly class ListCommunityContactsQuery
{
    public function __construct(public string $membershipId)
    {
    }
}
