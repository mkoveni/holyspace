<?php

declare(strict_types=1);

namespace App\Reporting\Application;

interface FollowupAccounts
{
    public function eligible(string $id, array $required): bool;

    public function name(string $id): string;

    /** @param list<string> $ids
     * @return array<string, string>
     */
    public function names(array $ids): array;

    public function assignees(array $required): array;
}
