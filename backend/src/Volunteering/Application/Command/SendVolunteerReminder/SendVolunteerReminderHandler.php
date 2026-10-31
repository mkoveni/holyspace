<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Command\SendVolunteerReminder;

use App\Communications\Application\Command\CreateCampaign\CreateCampaignCommand;
use App\Communications\Application\Command\CreateCampaign\CreateCampaignHandler;
use App\Communications\Application\Command\SaveMessageTemplate\SaveMessageTemplateCommand;
use App\Communications\Application\Command\SaveMessageTemplate\SaveMessageTemplateHandler;
use App\Events\Application\ReadModel\OccurrenceDirectory;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use App\Shared\Domain\ValueObject\Input;
use App\Volunteering\Application\Command\SettleVolunteerResponses\SettleVolunteerResponsesCommand;
use App\Volunteering\Application\Command\SettleVolunteerResponses\SettleVolunteerResponsesHandler;
use App\Volunteering\Domain\Model\VolunteerReminder;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SendVolunteerReminderHandler
{
    public function __construct(
        private VolunteeringRepository $records,
        private OccurrenceDirectory $occurrences,
        private WorkflowTransaction $transactions,
        private AccessControl $authorization,
        private ClockInterface $clock,
        private SettleVolunteerResponsesHandler $settleVolunteerResponsesHandler,
        private SaveMessageTemplateHandler $saveMessageTemplateHandler,
        private CreateCampaignHandler $createCampaignHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SendVolunteerReminderCommand $message): object
    {
        $id = $message->id;
        $channel = $message->channel;
        $reference = $message->reference;
        $this->manager();
        if (!$this->authorization->isGranted('communications.manage')) {
            throw new AccessDenied('Communications management permission is required.');
        }
        ($this->settleVolunteerResponsesHandler)(new SettleVolunteerResponsesCommand());

        return $this->transactions->run(function () use ($id, $channel, $reference) {
            Input::text($reference, 100);
            if (!in_array($channel, ['email', 'sms'], true)) {
                throw new InvalidArgumentException('Choose email or sms.');
            }
            foreach ($this->records->all('reminders') as $old) {
                if ($old->reference === $reference) {
                    if ($old->assignmentId !== $id || $old->channel !== $channel) {
                        throw new DomainException('Reminder reference was already used.');
                    }

                    return $old;
                }
            }
            $a = $this->required('assignments', $id);
            $shift = $this->required('shifts', $a->shiftId);
            $occurrence = $this->occurrences->get($shift->occurrenceId);
            if (!in_array($a->status, ['assigned', 'invited'], true) || 'open' !== $shift->status || !$occurrence || 'cancelled' === $occurrence['status'] || Input::instant($shift->endsAt) <= $this->clock->now()) {
                throw new DomainException('Review this assignment before preparing a reminder.');
            }
            $role = $this->required('roles', $shift->roleId);
            $body = 'Hello {{name}}, your '.$role->name.' shift is '.$shift->startsAt.' to '.$shift->endsAt.'. Sign in to the Volunteer workspace to review your schedule'.('invited' === $a->status ? ' and respond to your invitation.' : '.');
            $template = ($this->saveMessageTemplateHandler)(new SaveMessageTemplateCommand(['name' => 'Volunteer reminder', 'channel' => $channel, 'subject' => 'Volunteer shift reminder', 'body' => $body]));
            $campaign = ($this->createCampaignHandler)(new CreateCampaignCommand(['name' => 'Volunteer reminder '.$reference, 'templateId' => $template->id, 'recipientIds' => [$a->personId]]));
            $reminder = new VolunteerReminder($id, $channel, $reference, $campaign->id, VolunteerReminder::signature($occurrence), $shift->version, $a->status);
            $this->records->save($reminder);

            return $this->required('reminders', $reminder->id);
        });
    }

    private function manager(): void
    {
        if (!$this->authorization->isGranted('volunteering.manage')) {
            throw new AccessDenied('Volunteer management permission is required.');
        }
    }

    private function required(string $kind, string $id): object
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}
