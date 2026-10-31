<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\IdentityAccess\Infrastructure\Security\SymfonyUser;
use App\Shared\Domain\Model\WorkflowRecord;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

final readonly class WorkflowAuditJournal
{
    public function __construct(private Connection $db, private ?TokenStorageInterface $tokens = null)
    {
    }

    public function append(string $context, string $kind, WorkflowRecord $record): void
    {
        $user = $this->tokens?->getToken()?->getUser();
        $this->db->insert('workflow_audit', [
            'id' => Uuid::v7()->toRfc4122(),
            'context' => $context,
            'record_id' => $record->id,
            'kind' => $kind,
            'action' => 0 === $record->version ? 'created' : 'updated',
            'version' => $record->version + 1,
            'actor_id' => $user instanceof SymfonyUser ? $user->account()->id()->toString() : null,
            'occurred_at' => Clock::get()->now()->format(DATE_ATOM),
        ]);
    }
}
