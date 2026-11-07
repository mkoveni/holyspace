<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure;

use App\IdentityAccess\Application\Contract\AccountNames;
use App\IdentityAccess\Application\Contract\AccountReader;
use InvalidArgumentException;

final readonly class PublishedAccountNames implements AccountNames
{
    public function __construct(private AccountReader $accounts)
    {
    }

    public function names(array $ids): array
    {
        $unique = array_values(array_unique($ids));
        if (count($unique) > 100) {
            throw new InvalidArgumentException('Account names must be requested in pages of at most 100.');
        }
        $names = [];
        foreach ($unique as $id) {
            $account = $this->accounts->find($id);
            if (null !== $account) {
                $names[$id] = $account->displayName;
            }
        }

        return $names;
    }
}
