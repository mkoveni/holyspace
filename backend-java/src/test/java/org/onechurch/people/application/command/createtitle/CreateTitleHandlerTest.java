package org.onechurch.people.application.command.createtitle;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_AUDIT;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.dao.DuplicateKeyException;

class CreateTitleHandlerTest extends PeopleIntegrationTestSupport {
    @Autowired private CreateTitleHandler handler;

    @Test
    void auditsTheTitleCodeAndRollsBackDuplicateChanges() {
        asActor(admin, () -> {
            handler.handle(new CreateTitleCommand(adminId, "pastor", "Pastor"));
            assertThatThrownBy(() -> handler.handle(new CreateTitleCommand(adminId, "PASTOR", "Pastor")))
                .isInstanceOf(DuplicateKeyException.class);
            return null;
        });
        assertThat(db.select(PEOPLE_AUDIT.TARGET_REFERENCE)
            .from(PEOPLE_AUDIT)
            .where(PEOPLE_AUDIT.ACTION.eq("title.created"))
            .fetch(PEOPLE_AUDIT.TARGET_REFERENCE)).containsExactly("PASTOR");
    }
}
