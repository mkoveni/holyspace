<?php

declare(strict_types=1);

namespace App\People\Application\Command\ChangeMembershipStatus;

use App\People\Domain\Enum\MembershipStatus;
use App\People\Domain\Repository\PersonRepository;
use App\People\Domain\ValueObject\PersonId;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeMembershipStatusHandler
{
    public function __construct(private PersonRepository $people)
    {
    }

    public function __invoke(ChangeMembershipStatusCommand $c): void
    {
        $p = $this->people->findById(PersonId::fromString($c->personId));
        if (!$p) {
            throw new RuntimeException('Person not found.');
        }
        $p->changeStatus(MembershipStatus::from($c->status));
        $this->people->save($p);
    }
}
