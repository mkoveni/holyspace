package org.onechurch.volunteering.application.command.assignvolunteer;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_ASSIGNMENTS;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.VolunteerIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;

class AssignVolunteerHandlerIntegrationTest extends VolunteerIntegrationTestSupport {
    @Autowired AssignVolunteerHandler handler;
    @Test
    void directCommandRejectsAnonymousSpoofedAndRevokedCallersWithoutWriting() throws Exception {
        AssignVolunteerCommand valid =
            new AssignVolunteerCommand(adminId, UUID.randomUUID(), shift, 0, volunteer, "PROPOSED");
        assertIdentityFailure(() -> handler.handle(valid), IdentityException.Kind.UNAUTHENTICATED);
        assertIdentityFailure(
            () -> asActor(admin,
                () -> handler.handle(
                    new AssignVolunteerCommand(UUID.randomUUID(), UUID.randomUUID(), shift, 0, volunteer, "PROPOSED"))),
            IdentityException.Kind.FORBIDDEN);
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("volunteering.manage"))
            .fetchSingle(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
        assertIdentityFailure(() -> asActor(admin, () -> handler.handle(valid)), IdentityException.Kind.FORBIDDEN);
        assertThat(db.fetchCount(SERVING_ASSIGNMENTS)).isZero();
    }

    @Test
    void concurrentOverlappingDutiesCannotDoubleBookOneVolunteer() throws Exception {
        UUID other = add("/shifts", body("requestId", UUID.randomUUID(), "teamId", team, "roleId", role, "sourceKind",
            "STANDALONE", "sourceId", null, "startsAt", start, "endsAt", end, "positions", 2));
        CountDownLatch gate = new CountDownLatch(1);
        try (ExecutorService pool = Executors.newFixedThreadPool(2)) {
            Future<Integer> first = pool.submit(() -> {
                gate.await();
                return serving("POST", "/assignments", body("requestId", UUID.randomUUID(), "shiftId", shift,
                    "expectedVersion", 0, "volunteerId", volunteer, "status", "PROPOSED")).status();
            });
            Future<Integer> second = pool.submit(() -> {
                gate.await();
                return serving("POST", "/assignments", body("requestId", UUID.randomUUID(), "shiftId", other,
                    "expectedVersion", 0, "volunteerId", volunteer, "status", "PROPOSED")).status();
            });
            gate.countDown();
            assertThat(List.of(first.get(15, TimeUnit.SECONDS), second.get(15, TimeUnit.SECONDS)))
                .containsExactlyInAnyOrder(201, 409);
        }
        assertThat(db.fetchCount(SERVING_ASSIGNMENTS)).isEqualTo(1);
    }
}
