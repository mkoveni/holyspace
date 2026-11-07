<?php

declare(strict_types=1);

namespace App\People\Domain\ValueObject;

enum MembershipType: string
{
    case ActiveMember = 'ACTIVE_MEMBER';
    case RegularVisitor = 'REGULAR_VISITOR';
    case Visitor = 'VISITOR';
    case Transferred = 'TRANSFERRED';

    public function label(): string
    {
        return match ($this) {
            self::ActiveMember => 'Active Member',
            self::RegularVisitor => 'Regular Visitor',
            self::Visitor => 'Visitor',
            self::Transferred => 'Transferred',
        };
    }
}
