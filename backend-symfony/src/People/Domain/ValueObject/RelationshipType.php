<?php

declare(strict_types=1);

namespace App\People\Domain\ValueObject;

enum RelationshipType: string
{
    case ParentOf = 'PARENT_OF';
    case GuardianOf = 'GUARDIAN_OF';
    case SpouseOf = 'SPOUSE_OF';
    case SiblingOf = 'SIBLING_OF';
    case Other = 'OTHER';

    /** Symmetric relationships are stored once, with the smaller id as the source. */
    public function isSymmetric(): bool
    {
        return match ($this) {
            self::SpouseOf, self::SiblingOf, self::Other => true,
            self::ParentOf, self::GuardianOf => false,
        };
    }

    /** The name of the relationship as seen from the target person. */
    public function inverse(): string
    {
        return match ($this) {
            self::ParentOf => 'CHILD_OF',
            self::GuardianOf => 'WARD_OF',
            default => $this->value,
        };
    }
}
