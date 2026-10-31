package org.onechurch.people.domain;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.util.List;
import java.util.UUID;

class ParentagePolicyTest {

    private final Instant now = Instant.parse("2026-10-25T10:00:00Z");

    private PersonRelationship link(UUID source, UUID target, RelationshipType type) {
        return new PersonRelationship(UUID.randomUUID(), source, target, type, now, null, 0);
    }

    @Test
    void preventsDirectAndIndirectCyclesButAllowsMultipleParentsAndHistoricalChanges() {
        UUID a = UUID.randomUUID();
        UUID b = UUID.randomUUID();
        UUID c = UUID.randomUUID();
        PersonRelationship ab = link(a, b, RelationshipType.PARENT_OF);
        PersonRelationship bc = link(b, c, RelationshipType.PARENT_OF);
        assertThatThrownBy(
            () -> ParentagePolicy.requireAcyclic(link(c, a, RelationshipType.PARENT_OF), List.of(ab, bc), now))
            .isInstanceOf(PeopleException.class);
        ParentagePolicy.requireAcyclic(link(a, c, RelationshipType.PARENT_OF), List.of(ab, bc), now);
        ParentagePolicy.requireAcyclic(link(c, a, RelationshipType.PARENT_OF),
            List.of(ab.end(0, now.plusSeconds(1)), bc), now.plusSeconds(2));
    }

    @Test
    void canonicalizesSymmetricFactsAndRejectsSelfLinks() {
        UUID a = UUID.fromString("ffffffff-ffff-ffff-ffff-ffffffffffff");
        UUID b = UUID.fromString("00000000-0000-0000-0000-000000000001");
        assertThat(link(a, b, RelationshipType.SPOUSE_OF).sourceId()).isEqualTo(b);
        assertThat(link(b, a, RelationshipType.SPOUSE_OF).sourceId()).isEqualTo(b);
        assertThat(link(a, b, RelationshipType.PARENT_OF).sourceId()).isEqualTo(a);
        assertThatThrownBy(() -> link(a, a, RelationshipType.OTHER)).isInstanceOf(PeopleException.class);
    }
}
