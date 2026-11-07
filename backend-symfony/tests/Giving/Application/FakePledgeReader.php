<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\PledgeDetail;
use App\Giving\Application\Contract\PledgePage;
use App\Giving\Application\Contract\PledgeReader;

/** In-memory test double: knows no pledges and records each call. */
final class FakePledgeReader implements PledgeReader
{
    /** @var list<string> */
    public array $calls = [];

    public function list(?string $donorId, ?string $status, int $limit, int $offset): PledgePage
    {
        $this->calls[] = 'list:'.$donorId.':'.$status.":$limit:$offset";

        return new PledgePage([], 0);
    }

    public function detail(string $id, int $limit, int $offset): ?PledgeDetail
    {
        $this->calls[] = "detail:$id:$limit:$offset";

        return null;
    }

    public function candidates(string $id, int $limit, int $offset): ?array
    {
        $this->calls[] = "candidates:$id:$limit:$offset";

        return null;
    }
}
