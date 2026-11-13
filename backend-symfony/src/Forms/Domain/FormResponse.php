<?php

declare(strict_types=1);

namespace App\Forms\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * One stored answer set. An anonymous response carries no respondent and only a date, never a time of day; an identified one
 * carries the signed-in account and person. The expiry date is fixed when it is stored (the retention rule).
 */
final readonly class FormResponse
{
    /**
     * @param array<string, bool|float|int|string|list<string>> $answers
     */
    private function __construct(
        public string $id,
        public string $formId,
        public int $formVersion,
        public IdentityMode $identity,
        public string $submissionKey,
        public array $answers,
        public ?int $score,
        public ?int $maxScore,
        public ?string $nickname,
        public ?string $personId,
        public ?string $accountId,
        public DateTimeImmutable $submittedOn,
        public ?DateTimeImmutable $submittedAt,
        public DateTimeImmutable $expiresOn,
    ) {
    }

    /**
     * @param array<string, bool|float|int|string|list<string>> $answers
     */
    public static function submit(
        string $id,
        Form $form,
        string $submissionKey,
        array $answers,
        ?Score $score,
        ?string $nickname,
        ?string $personId,
        ?string $accountId,
        DateTimeImmutable $now,
    ): self {
        $identified = IdentityMode::Identified === $form->settings->identity;
        if (!$identified && (null !== $personId || null !== $accountId)) {
            throw FormException::invalid('An anonymous form cannot record who answered.');
        }
        if ($identified && null === $accountId) {
            throw FormException::invalid('An identified form needs the signed-in account.');
        }
        $nickname = null === $nickname ? null : trim(preg_replace('/\s+/u', ' ', $nickname) ?? '');
        if ('' === $nickname) {
            $nickname = null;
        }
        if (null !== $nickname && (mb_strlen($nickname) > 40 || 1 === preg_match('/[\x00-\x1f\x7f<>]/', $nickname))) {
            throw FormException::invalid('A nickname is at most 40 characters without markup.');
        }
        $day = $now->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);

        return new self(
            $id,
            $form->id,
            $form->currentVersion,
            $form->settings->identity,
            $submissionKey,
            $answers,
            $score?->score,
            $score?->maxScore,
            $nickname,
            $personId,
            $accountId,
            $day,
            $identified ? $now : null,
            $day->modify('+'.$form->settings->retentionMonths.' months'),
        );
    }
}
