<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\ReconciliationDetail;
use App\Giving\Application\Contract\ReconciliationPage;
use App\Giving\Application\Contract\ReconciliationReader;

/** In-memory test double: knows no batches and records each call. */
final class FakeReconciliationReader implements ReconciliationReader
{
    /** @var list<string> */
    public array $calls = [];

    public function list(int $limit, int $offset): ReconciliationPage
    {
        $this->calls[] = "list:$limit:$offset";

        return new ReconciliationPage([], 0);
    }

    public function detail(string $id): ?ReconciliationDetail
    {
        $this->calls[] = 'detail:'.$id;

        return null;
    }
}
