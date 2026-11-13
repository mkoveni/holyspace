<?php

declare(strict_types=1);

namespace App\Forms\Application\Command\SubmitPublicResponse;

use App\Forms\Application\Contract\FormKeys;
use App\Forms\Application\ResponseIntake;
use App\Forms\Application\SubmitResult;
use App\Forms\Domain\FormException;
use App\Forms\Domain\Repository\FormRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** An anonymous answer to a public form. Nothing identifies the respondent and no person record is read or made. */
final readonly class SubmitPublicResponseHandler implements CommandHandler
{
    public function __construct(private FormRepository $forms, private FormKeys $keys, private ResponseIntake $intake, private ClockInterface $clock)
    {
    }

    public function handle(SubmitPublicResponseCommand $c): SubmitResult
    {
        $now = $this->clock->now();
        $form = $this->forms->findByTokenHashForUpdate($this->keys->tokenHash($c->token));
        if (null === $form || !$form->isVisible($now)) {
            throw FormException::missing();
        }
        if (!$this->keys->challengeIsValid($form->id, $c->challenge, $now)) {
            throw FormException::invalid('This page has expired. Reload the form and try again.');
        }

        return $this->intake->accept($form, $c->formVersion, $c->submissionKey, $c->answers, $c->nickname, null, null, $now);
    }
}
