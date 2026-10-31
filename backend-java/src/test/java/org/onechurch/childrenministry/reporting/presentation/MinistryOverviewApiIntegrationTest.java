package org.onechurch.childrenministry.reporting.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
class MinistryOverviewApiIntegrationTest extends ChildrenIntegrationTestSupport {
    @Test
    void overviewIsReadOnlyAndDoesNotExposePrivateCareOrIncidentPayloads() throws Exception {
        int audits = db.fetchCount(CHILDREN_AUDIT);
        Response response = call("GET", "/overview", null);
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(200);
        assertThat(response.body())
            .contains("Africa/Johannesburg", "upcoming", "teams", "safeguarding", "incidents", "communications")
            .doesNotContain("description", "immediateAction", "restrictedNotes", "allergies");
        assertThat(db.fetchCount(CHILDREN_AUDIT)).isEqualTo(audits);
    }
    @Test
    void readOnlyPeopleStaffReceiveNoRestrictedOverviewSections() throws Exception {
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.in("children.manage", "children.safeguarding", "children.incidents.manage",
                    "children.support"))))
            .execute();
        Response response = call("GET", "/overview", null);
        assertThat(response.status()).isEqualTo(200);
        assertThat(response.body()).contains("\"teams\":null", "\"safeguarding\":null", "\"incidents\":null",
            "\"communications\":null");
    }
}
