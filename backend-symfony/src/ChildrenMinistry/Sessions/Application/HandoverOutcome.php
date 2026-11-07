<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;

/**
 * The result of a handover attempt. A wrong pickup code must be recorded and the record committed, so the handler
 * returns the refusal instead of throwing it inside the transaction; the caller raises it once the work is saved.
 */
final readonly class HandoverOutcome
{
    private function __construct(private ?AttendanceView $attendance, private ?ChildrenException $failure)
    {
    }

    public static function collected(AttendanceView $attendance): self
    {
        return new self($attendance, null);
    }

    public static function refused(ChildrenException $failure): self
    {
        return new self(null, $failure);
    }

    /** @throws ChildrenException the refusal, when the attempt was refused */
    public function attendance(): AttendanceView
    {
        return $this->attendance ?? throw $this->failure ?? ChildrenException::forbidden();
    }

    public function refusal(): ?ChildrenException
    {
        return $this->failure;
    }
}
