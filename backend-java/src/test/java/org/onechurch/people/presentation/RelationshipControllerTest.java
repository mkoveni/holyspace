package org.onechurch.people.presentation;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;

import java.util.Map;
import java.util.UUID;

class RelationshipControllerTest extends PeopleIntegrationTestSupport {

    @Test
    void derivesReverseFactsAndPreventsCyclesWithoutAssumingGuardianship() throws Exception {
        UUID parent = person("Parent");
        UUID child = person("Child");
        UUID grandchild = person("Grandchild");
        relationship(parent, child, "PARENT_OF");
        relationship(child, grandchild, "PARENT_OF");
        assertThat(request("GET", "/api/people/" + child + "/relationships", admin, null).body())
            .contains("CHILD_OF", "PARENT_OF")
            .doesNotContain("GUARDIAN_OF", "WARD_OF");
        assertThat(request("POST", "/api/relationships", admin,
            Map.of("sourceId", grandchild, "targetId", parent, "type", "PARENT_OF")).status()).isEqualTo(400);
        assertThat(request("POST", "/api/relationships", admin,
            Map.of("sourceId", parent, "targetId", parent, "type", "OTHER")).status()).isEqualTo(400);
        relationship(person("Another Parent"), child, "PARENT_OF");
        relationship(parent, child, "GUARDIAN_OF");
        assertThat(request("GET", "/api/people/" + child + "/relationships", admin, null).body()).contains("WARD_OF");
    }

    @Test
    void storesOneSymmetricFactAndRetainsEndedRelationships() throws Exception {
        UUID a = person("A");
        UUID b = person("B");
        UUID id = relationship(a, b, "SPOUSE_OF");
        assertThat(
            request("POST", "/api/relationships", admin, Map.of("sourceId", b, "targetId", a, "type", "SPOUSE_OF"))
                .status())
            .isEqualTo(409);
        assertThat(request("POST", "/api/relationships/" + id + "/end", admin, Map.of("expectedVersion", 1)).status())
            .isEqualTo(409);
        assertThat(request("POST", "/api/relationships/" + id + "/end", admin, Map.of("expectedVersion", 0)).status())
            .isEqualTo(204);
        UUID replacement = relationship(b, a, "SPOUSE_OF");
        assertThat(request("GET", "/api/people/" + a + "/relationships", admin, null).body()).contains(id.toString(),
            replacement.toString());
    }
}
