<?php
declare(strict_types=1);
namespace App\LifeEvents\Domain\Model;
use App\LifeEvents\Domain\Enum\ParticipantRole;
use App\LifeEvents\Domain\ValueObject\PersonId;
final readonly class LifeEventParticipant
{
    private function __construct(private PersonId $personId, private ParticipantRole $role) {}
    public static function create(PersonId $personId, ParticipantRole $role): self { return new self($personId,$role); }
    public function personId(): PersonId { return $this->personId; }
    public function role(): ParticipantRole { return $this->role; }
}
