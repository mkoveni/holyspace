<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Infrastructure\Persistence;

use App\ChildrenMinistry\Messaging\Application\CommunicationPage;
use App\ChildrenMinistry\Messaging\Application\CommunicationReceipt;
use App\ChildrenMinistry\Messaging\Application\CommunicationSummary;
use App\ChildrenMinistry\Messaging\Application\CommunicationView;
use App\ChildrenMinistry\Messaging\Application\Contract\GuardianCommunicationReader;
use App\ChildrenMinistry\Messaging\Domain\CommunicationStatus;
use App\ChildrenMinistry\Messaging\Domain\CommunicationType;
use App\ChildrenMinistry\Messaging\Domain\GuardianCommunication;
use App\ChildrenMinistry\Messaging\Domain\Repository\GuardianCommunicationRepository;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;

final readonly class DbalGuardianCommunicationStore implements GuardianCommunicationReader, GuardianCommunicationRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function lock(string $id): ?GuardianCommunication
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM children_guardian_communications WHERE id = :id FOR UPDATE', ['id' => $id]);

        return false === $row ? null : GuardianCommunication::reconstitute(
            (string) $row['id'],
            (string) $row['child_id'],
            (string) $row['authority_id'],
            (string) $row['guardian_id'],
            (string) $row['programme_id'],
            null === $row['policy_id'] ? null : (string) $row['policy_id'],
            null === $row['session_id'] ? null : (string) $row['session_id'],
            CommunicationType::from((string) $row['type']),
            (string) $row['message'],
            CalendarDate::parse((string) $row['due_on']),
            CommunicationStatus::from((string) $row['status']),
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
            (string) $row['updated_by'],
            Timestamps::fromDatabase((string) $row['updated_at']),
            (string) $row['contact_reference'],
            (int) $row['version'],
        );
    }

    public function insert(GuardianCommunication $c, string $requestId, string $requestHash): void
    {
        $this->connection->insert('children_guardian_communications', [
            'id' => $c->id,
            'child_id' => $c->childId,
            'authority_id' => $c->authorityId,
            'guardian_id' => $c->guardianId,
            'programme_id' => $c->programmeId,
            'policy_id' => $c->policyId,
            'session_id' => $c->sessionId,
            'type' => $c->type->value,
            'message' => $c->message,
            'due_on' => $c->dueOn->iso(),
            'status' => $c->status->value,
            'created_by' => $c->createdBy,
            'created_at' => Timestamps::toDatabase($c->createdAt),
            'request_id' => $requestId,
            'request_hash' => $requestHash,
            'updated_by' => $c->updatedBy,
            'updated_at' => Timestamps::toDatabase($c->updatedAt),
        ]);
    }

    public function update(GuardianCommunication $c): void
    {
        $changed = $this->connection->update(
            'children_guardian_communications',
            ['status' => $c->status->value, 'contact_reference' => $c->contactReference, 'updated_at' => Timestamps::toDatabase($c->updatedAt), 'updated_by' => $c->updatedBy, 'version' => $c->version],
            ['id' => $c->id, 'version' => $c->version - 1],
        );
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function receipt(string $actorId, string $requestId): ?CommunicationReceipt
    {
        $row = $this->connection->fetchAssociative('SELECT id, request_hash FROM children_guardian_communications WHERE created_by = :actor AND request_id = :request', ['actor' => $actorId, 'request' => $requestId]);

        return false === $row ? null : new CommunicationReceipt((string) $row['id'], trim((string) $row['request_hash']));
    }

    public function find(string $id): ?CommunicationView
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM children_guardian_communications WHERE id = :id', ['id' => $id]);
        if (false === $row) {
            return null;
        }

        return new CommunicationView(
            (string) $row['id'],
            (string) $row['child_id'],
            (string) $row['authority_id'],
            (string) $row['guardian_id'],
            (string) $row['programme_id'],
            null === $row['policy_id'] ? null : (string) $row['policy_id'],
            null === $row['session_id'] ? null : (string) $row['session_id'],
            CommunicationType::from((string) $row['type']),
            (string) $row['message'],
            CalendarDate::parse((string) $row['due_on']),
            CommunicationStatus::from((string) $row['status']),
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
            (string) $row['updated_by'],
            Timestamps::fromDatabase((string) $row['updated_at']),
            (string) $row['contact_reference'],
            (int) $row['version'],
        );
    }

    public function list(?string $childId, string $status, CalendarDate $on, int $limit, int $offset): CommunicationPage
    {
        $where = 'TRUE';
        $parameters = [];
        if (null !== $childId) {
            $where .= ' AND child_id = :child';
            $parameters['child'] = $childId;
        }
        if ('ALL' !== $status) {
            $where .= ' AND status = :status';
            $parameters['status'] = $status;
        }
        $overdue = (int) $this->connection->fetchOne("SELECT count(*) FROM children_guardian_communications WHERE $where AND status = 'DRAFT' AND due_on <= :on", $parameters + ['on' => $on->iso()]);
        $total = (int) $this->connection->fetchOne("SELECT count(*) FROM children_guardian_communications WHERE $where", $parameters);
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, child_id, guardian_id, programme_id, session_id, type, due_on, status, version FROM children_guardian_communications WHERE $where ORDER BY due_on, id LIMIT :limit OFFSET :offset",
            $parameters + ['limit' => $limit, 'offset' => $offset],
        );

        return new CommunicationPage(array_map(static fn (array $row): CommunicationSummary => new CommunicationSummary(
            (string) $row['id'],
            (string) $row['child_id'],
            (string) $row['guardian_id'],
            (string) $row['programme_id'],
            null === $row['session_id'] ? null : (string) $row['session_id'],
            CommunicationType::from((string) $row['type']),
            CalendarDate::parse((string) $row['due_on']),
            CommunicationStatus::from((string) $row['status']),
            (int) $row['version'],
        ), $rows), $total, $overdue);
    }
}
