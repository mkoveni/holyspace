package org.onechurch.people.application.command.createrelationship;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_RELATIONSHIPS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.RelationshipType;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;

class CreateRelationshipHandlerTest extends PeopleIntegrationTestSupport {
    @Autowired private CreateRelationshipHandler handler;
    @Test
    void serializesConcurrentReciprocalParentageToPreventACycle() throws Exception {
        UUID a = person("A");
        UUID b = person("B");
        CountDownLatch start = new CountDownLatch(1);
        try (ExecutorService pool = Executors.newFixedThreadPool(2)) {
            Future<Boolean> first = pool.submit(() -> attempt(a, b, start));
            Future<Boolean> second = pool.submit(() -> attempt(b, a, start));
            start.countDown();
            assertThat(List.of(first.get(15, TimeUnit.SECONDS), second.get(15, TimeUnit.SECONDS)))
                .containsExactlyInAnyOrder(true, false);
        }
        assertThat(db.fetchCount(PEOPLE_RELATIONSHIPS)).isEqualTo(1);
        assertThat(db.fetchCount(PEOPLE_AUDIT)).isEqualTo(3);
    }
    private boolean attempt(UUID source, UUID target, CountDownLatch start) throws Exception {
        start.await(10, TimeUnit.SECONDS);
        try {
            asActor(admin, () -> handler
                .handle(new CreateRelationshipCommand(adminId, source, target, RelationshipType.PARENT_OF)));
            return true;
        } catch (PeopleException error) {
            assertThat(error.kind()).isEqualTo(PeopleException.Kind.INVALID);
            return false;
        }
    }
    @Test
    void rejectsDirectAnonymousAndSpoofedActorCalls() throws Exception {
        UUID a = person("A");
        UUID b = person("B");
        assertIdentityFailure(
            () -> handler.handle(new CreateRelationshipCommand(adminId, a, b, RelationshipType.PARENT_OF)),
            IdentityException.Kind.UNAUTHENTICATED);
        assertIdentityFailure(
            () -> asActor(admin,
                () -> handler
                    .handle(new CreateRelationshipCommand(UUID.randomUUID(), a, b, RelationshipType.PARENT_OF))),
            IdentityException.Kind.FORBIDDEN);
    }
}
