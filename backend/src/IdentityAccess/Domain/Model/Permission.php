<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\Model;

use App\IdentityAccess\Domain\ValueObject\PermissionId;
use App\Shared\Domain\ValueObject\AuditTimestamps;
use DateTimeImmutable;
use InvalidArgumentException;

final class Permission
{
    private function __construct(
        private readonly PermissionId $id,
        private readonly string $code,
        private readonly string $description,
        private readonly AuditTimestamps $auditTimestamps,
    ) {}

    public static function create(PermissionId $id, string $code, string $description, DateTimeImmutable $now): self
    {
        $code = trim($code);
        if ($code === '') throw new InvalidArgumentException('Permission code cannot be empty.');
        return new self($id, $code, trim($description), new AuditTimestamps($now, $now));
    }

    public static function reconstitute(
        PermissionId $id,
        string $code,
        string $description,
        AuditTimestamps $auditTimestamps,
    ): self {
        return new self($id, trim($code), trim($description), $auditTimestamps);
    }

    public function id(): PermissionId { return $this->id; }
    public function code(): string { return $this->code; }
    public function description(): string { return $this->description; }
    public function createdAt(): DateTimeImmutable { return $this->auditTimestamps->createdAt(); }
    public function updatedAt(): DateTimeImmutable { return $this->auditTimestamps->updatedAt(); }
}
