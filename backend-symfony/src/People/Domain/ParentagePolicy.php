<?php

declare(strict_types=1);

namespace App\People\Domain;

use App\People\Domain\ValueObject\RelationshipType;
use DateTimeImmutable;

final class ParentagePolicy
{
    private function __construct()
    {
    }

    /**
     * Commands take effect now, so only simultaneously current parentage takes part in cycle detection.
     *
     * @param list<PersonRelationship> $graph
     */
    public static function requireAcyclic(PersonRelationship $proposed, array $graph, DateTimeImmutable $now): void
    {
        if (RelationshipType::ParentOf !== $proposed->type) {
            return;
        }

        $children = [];
        foreach ($graph as $edge) {
            if (RelationshipType::ParentOf === $edge->type && $edge->currentAt($now)) {
                $children[$edge->sourceId][] = $edge->targetId;
            }
        }

        $visited = [];
        $pending = [$proposed->targetId];
        while ([] !== $pending) {
            $node = array_shift($pending);
            if ($node === $proposed->sourceId) {
                throw PeopleException::invalid('Parentage cannot contain a cycle.');
            }
            if (!isset($visited[$node])) {
                $visited[$node] = true;
                array_push($pending, ...($children[$node] ?? []));
            }
        }
    }
}
