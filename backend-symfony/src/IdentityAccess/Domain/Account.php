<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain;

use App\IdentityAccess\Domain\ValueObject\AccountStatus;
use App\IdentityAccess\Domain\ValueObject\DisplayName;
use App\IdentityAccess\Domain\ValueObject\EmailAddress;
use App\IdentityAccess\Domain\ValueObject\PasswordHash;
use App\IdentityAccess\Domain\ValueObject\Username;
use App\Shared\Domain\Revision;
use DateTimeImmutable;

/**
 * Aggregate root identified by its id. State changes return a new aggregate that keeps the
 * identity and username; the Revision owns the version and the creation and update times.
 */
final readonly class Account
{
    private function __construct(
        public string $id,
        public Username $username,
        public EmailAddress $email,
        public DisplayName $displayName,
        public PasswordHash $passwordHash,
        public AccountStatus $status,
        public Revision $revision,
    ) {
    }

    public static function register(
        string $id,
        string $username,
        string $email,
        string $displayName,
        string $passwordHash,
        DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            new Username($username),
            new EmailAddress($email),
            new DisplayName($displayName),
            new PasswordHash($passwordHash),
            AccountStatus::Active,
            Revision::start($now),
        );
    }

    public static function reconstitute(
        string $id,
        string $username,
        string $email,
        string $displayName,
        string $passwordHash,
        string $status,
        Revision $revision,
    ): self {
        return new self(
            $id,
            new Username($username),
            new EmailAddress($email),
            new DisplayName($displayName),
            new PasswordHash($passwordHash),
            AccountStatus::fromValue($status),
            $revision,
        );
    }

    /** @param int|null $expectedVersion the version the caller saw; null skips the optimistic check */
    public function updateProfile(string $email, string $displayName, ?int $expectedVersion, DateTimeImmutable $now): self
    {
        return new self(
            $this->id,
            $this->username,
            new EmailAddress($email),
            new DisplayName($displayName),
            $this->passwordHash,
            $this->status,
            $this->revise($expectedVersion, $now),
        );
    }

    public function changePassword(string $hash, ?int $expectedVersion, DateTimeImmutable $now): self
    {
        return new self(
            $this->id,
            $this->username,
            $this->email,
            $this->displayName,
            new PasswordHash($hash),
            $this->status,
            $this->revise($expectedVersion, $now),
        );
    }

    public function enable(?int $expectedVersion, DateTimeImmutable $now): self
    {
        return $this->withStatus(AccountStatus::Active, $expectedVersion, $now);
    }

    public function disable(?int $expectedVersion, DateTimeImmutable $now): self
    {
        return $this->withStatus(AccountStatus::Disabled, $expectedVersion, $now);
    }

    public function isActive(): bool
    {
        return AccountStatus::Active === $this->status;
    }

    public function hasSameIdentityAs(self $other): bool
    {
        return $this->id === $other->id;
    }

    private function withStatus(AccountStatus $status, ?int $expectedVersion, DateTimeImmutable $now): self
    {
        return new self(
            $this->id,
            $this->username,
            $this->email,
            $this->displayName,
            $this->passwordHash,
            $status,
            $this->revise($expectedVersion, $now),
        );
    }

    private function revise(?int $expectedVersion, DateTimeImmutable $now): Revision
    {
        return null === $expectedVersion ? $this->revision->advance($now) : $this->revision->next($expectedVersion, $now);
    }
}
