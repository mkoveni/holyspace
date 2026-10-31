<?php

declare(strict_types=1);

namespace App\LifeEvents\Application\Service;

use App\LifeEvents\Domain\Enum\LifeEventType;
use App\LifeEvents\Domain\Enum\ParticipantRole;
use App\LifeEvents\Domain\Model\LifeEventParticipant;
use App\LifeEvents\Domain\ValueObject\PersonId;
use InvalidArgumentException;

final class ParticipantInput
{
    /** @return list<LifeEventParticipant> */
    public static function parse(array $input, LifeEventType $type): array
    {
        $participants = [];
        foreach (array_values($input) as $index => $participant) {
            // Accept the ID-only payload used by earlier clients.
            $defaultRole = match ($type) {
                LifeEventType::MARRIAGE => 'spouse',
                LifeEventType::ENGAGEMENT => 0 === $index ? 'proposer' : 'recipient',
                default => 'subject',
            };
            if (is_string($participant)) {
                $participant = ['personId' => $participant, 'role' => $defaultRole];
            }
            if (!is_array($participant) || !isset($participant['personId']) || !is_string($participant['personId'])
                || (isset($participant['role']) && !is_string($participant['role']))) {
                throw new InvalidArgumentException('Each participant must contain a personId and an optional role.');
            }
            $participants[] = LifeEventParticipant::create(
                PersonId::fromString($participant['personId']),
                ParticipantRole::from($participant['role'] ?? $defaultRole),
            );
        }

        return $participants;
    }
}
