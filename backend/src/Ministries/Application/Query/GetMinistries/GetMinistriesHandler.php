<?php

declare(strict_types=1);

namespace App\Ministries\Application\Query\GetMinistries;

use App\Ministries\Application\ReadModel\MinistriesReadStore;

final readonly class GetMinistriesHandler
{
    public function __construct(private MinistriesReadStore $records)
    {
    }

    public function __invoke(GetMinistriesQuery $message): array
    {
        return ['ministries' => $this->records->all('ministries'), 'memberships' => $this->records->all('memberships')];
    }
}
