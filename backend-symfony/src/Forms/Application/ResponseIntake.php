<?php

declare(strict_types=1);

namespace App\Forms\Application;

use App\Forms\Application\Contract\FormKeys;
use App\Forms\Domain\Form;
use App\Forms\Domain\FormException;
use App\Forms\Domain\FormResponse;
use App\Forms\Domain\IdentityMode;
use App\Forms\Domain\Repository\FormRepository;
use App\Forms\Domain\Repository\ResponseRepository;
use App\Shared\Application\IdGenerator;
use DateTimeImmutable;

/**
 * Stores one response to a locked, published form. Public and internal submit handlers share it, so the rules cannot drift:
 * idempotent retries, the response limit and window, one response per account, and anonymity.
 */
final readonly class ResponseIntake
{
    public function __construct(
        private FormRepository $forms,
        private ResponseRepository $responses,
        private FormKeys $keys,
        private IdGenerator $ids,
    ) {
    }

    /**
     * @param array<mixed> $answers
     */
    public function accept(Form $form, int $formVersion, string $clientKey, array $answers, ?string $nickname, ?string $accountId, ?string $personId, DateTimeImmutable $now): SubmitResult
    {
        if ($formVersion !== $form->currentVersion) {
            throw FormException::conflict('This form was updated. Reload it and answer again.');
        }
        if ('' === $clientKey || \strlen($clientKey) > 100) {
            throw FormException::invalid('A submission key is required.');
        }
        $key = $this->keys->submissionKey($form->id, $clientKey);
        if ($this->responses->hasSubmission($form->id, $key)) {
            return SubmitResult::received();
        }
        if (!$form->accepts($now, $this->responses->count($form->id))) {
            throw FormException::conflict('This form is not accepting answers.');
        }
        $definition = $this->forms->definition($form->id, $form->currentVersion) ?? throw FormException::missing();
        $clean = $definition->validate($answers);
        $score = $form->settings->isQuiz() ? $definition->score($clean) : null;

        $identified = IdentityMode::Identified === $form->settings->identity;
        if ($identified) {
            if (null === $accountId || $this->responses->hasAccountResponse($form->id, $accountId)) {
                throw FormException::conflict('You have already answered this form.');
            }
        } elseif (null !== $accountId && !$this->responses->insertMark($form->id, $this->keys->participationMark($form->id, $accountId))) {
            throw FormException::conflict('You have already answered this form.');
        }

        $this->responses->insert(FormResponse::submit(
            $this->ids->uuid(),
            $form,
            $key,
            $clean,
            $score,
            $nickname,
            $identified ? $personId : null,
            $identified ? $accountId : null,
            $now,
        ));

        $settings = $form->settings;

        return new SubmitResult(
            $settings->showScore ? $score?->score : null,
            $settings->showScore ? $score?->maxScore : null,
            $settings->showAnswers && null !== $score ? $definition->answerKey() : null,
        );
    }
}
