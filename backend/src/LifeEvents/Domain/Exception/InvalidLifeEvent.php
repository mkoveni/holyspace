<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Exception;
use RuntimeException;
final class InvalidLifeEvent extends RuntimeException
{
    public static function requiresParticipants(): self { return new self('A life event requires at least one participant.'); }
    public static function requiresTwoParticipants(string $type): self { return new self(sprintf('%s requires exactly two participants.',$type)); }
    public static function duplicateParticipants(): self { return new self('A life event cannot contain the same person more than once.'); }
    public static function invalidDetails(string $type): self { return new self(sprintf('Invalid details supplied for life event type "%s".',$type)); }
    public static function invalidMarriageParticipants(): self { return new self('A marriage requires exactly two spouse participants and neither spouse may be the same person.'); }
    public static function invalidEngagementParticipants(): self { return new self('An engagement requires exactly two participants: proposer and recipient.'); }
    public static function cannotCancelCompleted(): self { return new self('A completed life event cannot be cancelled.'); }
}
