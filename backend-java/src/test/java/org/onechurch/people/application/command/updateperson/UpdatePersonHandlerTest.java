package org.onechurch.people.application.command.updateperson;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_AUDIT;

import org.junit.jupiter.api.Test;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.PersonDetails;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;

class UpdatePersonHandlerTest extends PeopleIntegrationTestSupport {
    @Autowired private UpdatePersonHandler handler;
    @Test
    void concurrentEditsWithTheSameVersionCannotOverwriteEachOther() throws Exception {
        UUID person = person("Solly");
        CountDownLatch start = new CountDownLatch(1);
        try (ExecutorService pool = Executors.newFixedThreadPool(2)) {
            Future<Boolean> first = pool.submit(() -> attempt(person, "First", start));
            Future<Boolean> second = pool.submit(() -> attempt(person, "Second", start));
            start.countDown();
            assertThat(List.of(first.get(15, TimeUnit.SECONDS), second.get(15, TimeUnit.SECONDS)))
                .containsExactlyInAnyOrder(true, false);
        }
        assertThat(personVersion(person)).isEqualTo(1);
        assertThat(db.fetchCount(PEOPLE_AUDIT)).isEqualTo(2);
    }
    private boolean attempt(UUID person, String name, CountDownLatch start) throws Exception {
        start.await(10, TimeUnit.SECONDS);
        try {
            asActor(admin, () -> {
                handler.handle(new UpdatePersonCommand(adminId, person, 0,
                    new PersonDetails(null, name, "Mabasa", null, null, null, null), MembershipType.ACTIVE_MEMBER));
                return null;
            });
            return true;
        } catch (PeopleException error) {
            assertThat(error.kind()).isEqualTo(PeopleException.Kind.CONFLICT);
            return false;
        }
    }
}
