<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Command\SaveChildSupport;

use App\ChildrenMinistry\Application\Query\GetChildSupport\GetChildSupportHandler;
use App\ChildrenMinistry\Application\Query\GetChildSupport\GetChildSupportQuery;
use App\ChildrenMinistry\Domain\Model\ChildSupportNote;
use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Outbox\IntegrationProcessor;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveChildSupportHandler
{
    public function __construct(
        private ChildrenMinistryRepository $records,
        private WorkflowTransaction $transactions,
        private IntegrationProcessor $outbox,
        private AccessControl $access,
        private GetChildSupportHandler $getChildSupportHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SaveChildSupportCommand $message): array
    {
        $sessionId = $message->sessionId;
        $childId = $message->childId;
        $text = $message->text;
        $expectedVersion = $message->expectedVersion;
        $this->outbox->drainAll();

        return $this->transactions->run(function () use ($sessionId, $childId, $text, $expectedVersion) {
            ($this->getChildSupportHandler)(new GetChildSupportQuery($sessionId, $childId));
            if (!$this->access->isGranted('children.manage')) {
                throw new AccessDenied('Children management permission is required to edit support notes.');
            }
            $note = $this->note($childId);
            if (($note?->version ?? 0) !== $expectedVersion) {
                throw new DomainException('Support note changed. Reload before saving.');
            }
            $this->records->save($note ? $note->revise($text) : new ChildSupportNote($childId, trim($text)));

            return ($this->getChildSupportHandler)(new GetChildSupportQuery($sessionId, $childId));
        });
    }

    private function note(string $id): ?ChildSupportNote
    {
        foreach ($this->records->all('supportnotes') as $note) {
            if ($note->childId === $id) {
                return $note;
            }
        }

        return null;
    }
}
