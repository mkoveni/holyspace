<?php

declare(strict_types=1);

namespace App\Forms\Domain\Model;

use App\Shared\Domain\Model\WorkflowRecord;
use App\Shared\Domain\ValueObject\Input;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\Clock\Clock;

final class FormSubmission extends WorkflowRecord
{
    public function __construct(
        public readonly string $formId,
        public readonly int $formVersion,
        public readonly array $snapshot,
        public readonly array $answers,
        public readonly string $submissionKey,
        public readonly string $status = 'pending',
        public readonly ?string $personId = null,
        public readonly ?string $registrationId = null,
        public readonly ?string $reviewNote = null,
        public readonly ?string $reviewedAt = null,
        string $id = '',
        string $createdAt = '',
        string $updatedAt = '',
        int $version = 0,
    ) {
        parent::__construct($id, $createdAt, $updatedAt, $version);
        Input::id($formId);
        Input::id($submissionKey);
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Invalid submission status.');
        }
    }

    public function review(string $status, string $note, ?string $personId, ?string $registrationId): self
    {
        if ('pending' !== $this->status) {
            throw new DomainException('This submission has already been reviewed.');
        }
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose approved or rejected.');
        }
        Input::text($note, 1000);

        return $this->changed(['status' => $status, 'reviewNote' => $note, 'personId' => $personId, 'registrationId' => $registrationId, 'reviewedAt' => Clock::get()->now()->format(DATE_ATOM)]);
    }
}
