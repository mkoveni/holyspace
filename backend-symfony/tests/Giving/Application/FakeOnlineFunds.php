<?php

declare(strict_types=1);

namespace App\Tests\Giving\Application;

use App\Giving\Application\Contract\OnlineFund;
use App\Giving\Application\Contract\OnlineFundDirectory;

/** In-memory test double: returns the catalogue a test sets and records the mode asked for. */
final class FakeOnlineFunds implements OnlineFundDirectory
{
    /** @var list<OnlineFund> */
    public array $catalogue = [];
    /** @var list<bool> */
    public array $calls = [];

    public function funds(bool $management): array
    {
        $this->calls[] = $management;

        return $this->catalogue;
    }

    public function enabled(string $fundId): bool
    {
        return false;
    }
}
