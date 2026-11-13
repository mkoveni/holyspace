<?php

declare(strict_types=1);

namespace App\Forms\Domain;

use App\Shared\Domain\Revision;
use DateTimeImmutable;

/**
 * A form or quiz. Draft forms are editable; publishing freezes the draft into an immutable numbered version, and each
 * response records the version it answered. Audience and identity mode are a promise to respondents, so they only change
 * while the form is a draft (the database refuses it once a response exists).
 */
final readonly class Form
{
    private function __construct(
        public string $id,
        public string $ownerAccountId,
        public FormSettings $settings,
        public FormStatus $status,
        public ?string $tokenHash,
        public int $currentVersion,
        public Revision $revision,
    ) {
    }

    public static function create(string $id, string $ownerAccountId, FormSettings $settings, DateTimeImmutable $now): self
    {
        return new self($id, $ownerAccountId, $settings, FormStatus::Draft, null, 0, Revision::start($now));
    }

    public static function reconstitute(string $id, string $ownerAccountId, FormSettings $settings, FormStatus $status, ?string $tokenHash, int $currentVersion, Revision $revision): self
    {
        return new self($id, $ownerAccountId, $settings, $status, $tokenHash, $currentVersion, $revision);
    }

    public function edit(FormSettings $settings, int $expectedVersion, DateTimeImmutable $now): self
    {
        if (FormStatus::Draft !== $this->status) {
            throw FormException::conflict('Unpublish the form before editing it.');
        }

        return new self($this->id, $this->ownerAccountId, $settings, $this->status, null, $this->currentVersion, $this->revision->next($expectedVersion, $now));
    }

    /** @param string|null $tokenHash the hash of a fresh public link token; required for, and only for, public forms */
    public function publish(?string $tokenHash, int $expectedVersion, DateTimeImmutable $now): self
    {
        if (FormStatus::Draft !== $this->status) {
            throw FormException::conflict('Only a draft can be published.');
        }
        if ((Audience::Public === $this->settings->audience) !== (null !== $tokenHash)) {
            throw FormException::invalid('A public form, and only a public form, has a link token.');
        }

        return new self($this->id, $this->ownerAccountId, $this->settings, FormStatus::Published, $tokenHash, $this->currentVersion + 1, $this->revision->next($expectedVersion, $now));
    }

    public function close(int $expectedVersion, DateTimeImmutable $now): self
    {
        if (FormStatus::Published !== $this->status) {
            throw FormException::conflict('Only a published form can be closed.');
        }

        return $this->with(FormStatus::Closed, $this->tokenHash, $expectedVersion, $now);
    }

    public function reopen(int $expectedVersion, DateTimeImmutable $now): self
    {
        if (FormStatus::Closed !== $this->status) {
            throw FormException::conflict('Only a closed form can be reopened.');
        }

        return $this->with(FormStatus::Published, $this->tokenHash, $expectedVersion, $now);
    }

    /** Back to a draft for editing; the old public link stops working and the next publish makes a new version. */
    public function unpublish(int $expectedVersion, DateTimeImmutable $now): self
    {
        if (FormStatus::Draft === $this->status) {
            throw FormException::conflict('The form is already a draft.');
        }

        return $this->with(FormStatus::Draft, null, $expectedVersion, $now);
    }

    /** Whether answers are accepted now, given how many were already stored. */
    public function accepts(DateTimeImmutable $now, int $responses): bool
    {
        return FormStatus::Published === $this->status
            && (null === $this->settings->opensAt || $this->settings->opensAt <= $now)
            && (null === $this->settings->closesAt || $now < $this->settings->closesAt)
            && (null === $this->settings->maxResponses || $responses < $this->settings->maxResponses);
    }

    public function isVisible(DateTimeImmutable $now): bool
    {
        return FormStatus::Published === $this->status
            && (null === $this->settings->opensAt || $this->settings->opensAt <= $now);
    }

    private function with(FormStatus $status, ?string $tokenHash, int $expectedVersion, DateTimeImmutable $now): self
    {
        return new self($this->id, $this->ownerAccountId, $this->settings, $status, $tokenHash, $this->currentVersion, $this->revision->next($expectedVersion, $now));
    }
}
