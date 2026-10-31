<?php

declare(strict_types=1);

namespace App\People\Application\Query\SearchPeople;

use App\People\Application\DTO\PersonSearchResult;
use App\People\Application\ReadModel\PersonSearchReader;

final readonly class SearchPeopleHandler
{
    public function __construct(private PersonSearchReader $reader)
    {
    }

    public function __invoke(SearchPeopleQuery $message): PersonSearchResult
    {
        return $this->reader->search($message->term, $message->limit, $message->excludeIds, $message->ids);
    }
}
