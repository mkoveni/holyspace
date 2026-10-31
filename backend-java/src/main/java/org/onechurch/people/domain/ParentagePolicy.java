package org.onechurch.people.domain;

import java.time.Instant;
import java.util.ArrayDeque;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.UUID;

public final class ParentagePolicy {

    private ParentagePolicy() {
    }
    /** Commands take effect now. Only simultaneously current parentage participates in cycle detection. */

    public static void requireAcyclic(PersonRelationship proposed, List<PersonRelationship> graph, Instant now) {
        if (proposed.type() != RelationshipType.PARENT_OF) {
            return;
        }
        Set<UUID> visited = new HashSet<>();
        ArrayDeque<UUID> pending = new ArrayDeque<>();
        pending.add(proposed.targetId());
        while (!pending.isEmpty()) {
            UUID node = pending.removeFirst();
            if (node.equals(proposed.sourceId())) {
                throw PeopleException.invalid("Parentage cannot contain a cycle.");
            }
            if (visited.add(node)) {
                graph.stream()
                    .filter(edge -> edge.type() == RelationshipType.PARENT_OF && edge.currentAt(now))
                    .filter(edge -> edge.sourceId().equals(node))
                    .map(PersonRelationship::targetId)
                    .forEach(pending::addLast);
            }
        }
    }
}
