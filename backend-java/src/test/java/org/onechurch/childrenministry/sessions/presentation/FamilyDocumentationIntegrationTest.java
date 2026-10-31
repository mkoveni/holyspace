package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;

import java.util.Map;
class FamilyDocumentationIntegrationTest extends IdentityIntegrationTestSupport {
    @Test
    void familyModelsAreDistinctFromStaffModelsAndDoNotDescribeRecursiveAttendance() throws Exception {
        Response document = request("GET", "/v3/api-docs", null, null);
        assertThat(document.status()).isEqualTo(200);
        Map<?, ?> schemas = (Map<?, ?>) ((Map<?, ?>) document.data().get("components")).get("schemas");
        assertThat(properties(schemas, "FamilyProgramme").containsKey("readiness")).isTrue();
        assertThat(properties(schemas, "FamilyProgramme").containsKey("validFrom")).isTrue();
        assertThat(properties(schemas, "Programme").containsKey("minStaff")).isTrue();
        assertThat(properties(schemas, "Programme").containsKey("readiness")).isFalse();
        assertThat(properties(schemas, "FamilySession").containsKey("startsAt")).isTrue();
        assertThat(properties(schemas, "FamilySession").containsKey("session")).isFalse();
        assertThat(properties(schemas, "FamilyAttendance").containsKey("arrivedAt")).isTrue();
        assertThat(properties(schemas, "FamilyAttendance").containsKey("labelHash")).isFalse();
        assertThat(((Map<?, ?>) properties(schemas, "FamilyVisit").get("visit")).get("$ref"))
            .isEqualTo("#/components/schemas/FamilyAttendance");
    }
    private Map<?, ?> properties(Map<?, ?> schemas, String name) {
        return (Map<?, ?>) ((Map<?, ?>) schemas.get(name)).get("properties");
    }
}
