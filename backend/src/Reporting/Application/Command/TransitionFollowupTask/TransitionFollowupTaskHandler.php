<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\TransitionFollowupTask;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskHandler;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskQuery;
use App\Reporting\Domain\Repository\ReportingRepository;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class TransitionFollowupTaskHandler
{
    public function __construct(
        private ReportingRepository $records,
        private WorkflowTransaction $transactions,
        private AuthenticatedAccount $tokens,
        private ClockInterface $clock,
        private GetFollowupTaskHandler $getFollowupTaskHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(TransitionFollowupTaskCommand $message): array
    {
        $id = $message->id;
        $version = $message->version;
        $status = $message->status;
        $note = $message->note;

        return $this->transactions->run(function () use ($id, $version, $status, $note) {
            ($this->getFollowupTaskHandler)(new GetFollowupTaskQuery($id));
            $t = $this->required('tasks', $id);
            if ($t->version !== $version) {
                throw new DomainException('Task changed. Reload before updating.');
            }
            $this->records->save($t->transition($status, $note, $this->userId(), $this->clock->now()->format(DATE_ATOM)));

            return ($this->getFollowupTaskHandler)(new GetFollowupTaskQuery($id));
        });
    }

    private function required(string $kind, string $id): mixed
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }

    private function userId(): string
    {
        $user = $this->tokens->user();
        if (null === $user || 'active' !== $user->status()->value) {
            throw new AccessDenied('An active staff account is required.');
        }

        return $user->id()->toString();
    }
}
