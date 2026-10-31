package org.onechurch.childrenministry.sessions.application.command.checkinchild;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;
class CheckInChildHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private CheckInChildHandler handler;
    @Test
    void directHandlerRejectsAnonymousSpoofedAndRevokedPermissions() throws Exception {
        CheckInChildCommand command = new CheckInChildCommand(adminId, session, child, UUID.randomUUID());
        assertIdentityFailure(() -> handler.handle(command), IdentityException.Kind.UNAUTHENTICATED);
        asActor(admin, () -> {
            assertIdentityFailure(
                () -> handler.handle(new CheckInChildCommand(UUID.randomUUID(), session, child, UUID.randomUUID())),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        asActor(admin, () -> {
            UUID permission = db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.eq("children.station"))
                .fetchOne(PERMISSIONS.ID);
            db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
            assertIdentityFailure(() -> handler.handle(command), IdentityException.Kind.FORBIDDEN);
            return null;
        });
        assertThat(db.fetchCount(CHILDREN_ATTENDANCE)).isZero();
    }
    @Test
    void simultaneousArrivalsCannotOverbookLastSeat() throws Exception {
        UUID other = datedPerson("Other child", today.minusYears(9));
        UUID otherEnrolment = enrol(other);
        assign(other, otherEnrolment, group);
        UUID otherAuthority = create("/guardian-authorities", body("childId", other, "guardianId", guardian,
            "validFrom", from, "validUntil", until, "evidenceReference", "verified/other"));
        consent(other, otherAuthority, policy);
        db.update(CHILDREN_SESSIONS)
            .set(CHILDREN_SESSIONS.SEAT_LIMIT, 1)
            .where(CHILDREN_SESSIONS.ID.eq(session))
            .execute();
        CountDownLatch ready = new CountDownLatch(2);
        CountDownLatch start = new CountDownLatch(1);
        try (java.util.concurrent.ExecutorService executor = Executors.newVirtualThreadPerTaskExecutor()) {
            Future<Integer> first = executor.submit(() -> {
                ready.countDown();
                start.await(10, TimeUnit.SECONDS);
                return arrive(child, session, UUID.randomUUID()).status();
            });
            Future<Integer> second = executor.submit(() -> {
                ready.countDown();
                start.await(10, TimeUnit.SECONDS);
                return arrive(other, session, UUID.randomUUID()).status();
            });
            assertThat(ready.await(10, TimeUnit.SECONDS)).isTrue();
            start.countDown();
            assertThat(List.of(first.get(20, TimeUnit.SECONDS), second.get(20, TimeUnit.SECONDS)))
                .containsExactlyInAnyOrder(200, 409);
        }
        assertThat(db.fetchCount(CHILDREN_ATTENDANCE, CHILDREN_ATTENDANCE.CHECKED_OUT_AT.isNull())).isEqualTo(1);
    }
}
