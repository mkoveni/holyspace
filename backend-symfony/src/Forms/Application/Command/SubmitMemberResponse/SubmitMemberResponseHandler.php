<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\SubmitMemberResponse;

use App\Forms\Application\AudienceGate;
use App\Forms\Application\ResponseIntake;
use App\Forms\Application\SubmitResult;
use App\Forms\Domain\Audience;
use App\Forms\Domain\FormException;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** A signed-in answer to a members, staff or group form. The audience is rechecked here, under the form lock. */
final readonly class SubmitMemberResponseHandler implements CommandHandler
{
    public function __construct(private FormRepository $forms, private AudienceGate $gate, private ResponseIntake $intake, private ClockInterface $clock)
    {
    }

    public function handle(SubmitMemberResponseCommand $c): SubmitResult
    {
        $now = $this->clock->now();
        $form = $this->forms->findForUpdate($c->formId);
        if (null === $form || Audience::Public === $form->settings->audience || !$form->isVisible($now)) {
            throw FormException::missing();
        }
        $person = $this->gate->require($form->settings->audience, $form->settings->groupIds, $c->actorId, $now);

        return $this->intake->accept($form, $c->formVersion, $c->submissionKey, $c->answers, $c->nickname, $c->actorId, $person, $now);
    }
}
