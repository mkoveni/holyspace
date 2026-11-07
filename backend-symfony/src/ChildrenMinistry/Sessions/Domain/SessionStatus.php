<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Domain;

enum SessionStatus: string
{
    case Planned = 'PLANNED';
    case Open = 'OPEN';
    case NeedsReview = 'NEEDS_REVIEW';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';

    public function terminal(): bool
    {
        return self::Closed === $this || self::Cancelled === $this;
    }
}
