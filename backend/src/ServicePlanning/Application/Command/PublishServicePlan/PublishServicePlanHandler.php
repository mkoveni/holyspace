<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Command\PublishServicePlan;

use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\ServicePlanning\Domain\Model\ServicePlan;
use App\ServicePlanning\Domain\Repository\ServicePlanningRepository;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use App\Volunteering\Application\Command\SettleVolunteerResponses\SettleVolunteerResponsesCommand;
use App\Volunteering\Application\Command\SettleVolunteerResponses\SettleVolunteerResponsesHandler;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class PublishServicePlanHandler
{
    public function __construct(
        private ServicePlanningRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private AccessControl $authorization,
        private SettleVolunteerResponsesHandler $settleVolunteerResponsesHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(PublishServicePlanCommand $message): object
    {
        $id = $message->id;
        $version = $message->version;
        $published = $message->published;
        $this->manager();
        ($this->settleVolunteerResponsesHandler)(new SettleVolunteerResponsesCommand());

        return $this->transactions->run(function () use ($id, $version, $published) {
            $plan = $this->required($id);
            if ($plan->version !== $version) {
                throw new DomainException('Plan changed. Reload before publishing.');
            }
            if ($published) {
                $this->validateSchedule($plan);
            }
            $this->records->save($plan->changeStatus($published ? 'published' : 'draft'));

            return $this->required($id);
        });
    }

    private function manager(): void
    {
        if (!$this->authorization->isGranted('service_planning.manage')) {
            throw new AccessDenied('Service planning management is required.');
        }
    }

    private function required(string $id): ServicePlan
    {
        return $this->records->find('plans', $id) ?? throw new OutOfBoundsException('Plan not found.');
    }

    private function validateSchedule(ServicePlan $plan): void
    {
        $o = $this->occurrences->get($plan->occurrenceId) ?? throw new OutOfBoundsException('Occurrence not found.');
        if ('cancelled' === $o['status']) {
            throw new DomainException('Cannot plan a cancelled occurrence.');
        }
        if (array_sum(array_column($plan->items, 'durationMinutes')) * 60 > (new DateTimeImmutable($o['endsAt']))->getTimestamp() - (new DateTimeImmutable($o['startsAt']))->getTimestamp()) {
            throw new InvalidArgumentException('Service order exceeds the event duration.');
        }
    }
}
