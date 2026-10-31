<?php

declare(strict_types=1);

namespace App\ServicePlanning\Application\Command\SaveServicePlan;

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

final readonly class SaveServicePlanHandler
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
    public function __invoke(SaveServicePlanCommand $message): object
    {
        $occurrenceId = $message->occurrenceId;
        $title = $message->title;
        $items = $message->items;
        $id = $message->id;
        $version = $message->version;
        $this->manager();
        ($this->settleVolunteerResponsesHandler)(new SettleVolunteerResponsesCommand());

        return $this->transactions->run(function () use ($occurrenceId, $title, $items, $id, $version) {
            $plan = null === $id ? new ServicePlan($occurrenceId, $title, $items) : $this->required($id);
            if (null !== $id) {
                if ($plan->version !== $version || $plan->occurrenceId !== $occurrenceId) {
                    throw new DomainException('Plan changed or occurrence cannot be changed.');
                }
                $plan = $plan->edit($title, $items);
            }
            foreach ($this->records->all('plans') as $old) {
                if ($old->occurrenceId === $occurrenceId && $old->id !== $plan->id) {
                    throw new DomainException('This occurrence already has a service plan.');
                }
            }
            $this->validateSchedule($plan);
            $this->records->save($plan);

            return $this->required($plan->id);
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
