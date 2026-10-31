package org.onechurch.childrenministry.sessions.application.command.checkoutchild;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE_EVENTS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.util.List;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;
class CheckOutChildHandlerIntegrationTest extends ChildrenIntegrationTestSupport {
    @Test
    void concurrentHandoversCannotCollectTwice() throws Exception {
        UUID attendance = attendanceId(arrival());
        String code = (String) label(attendance, 0, "").data().get("pickupCode");
        CountDownLatch ready = new CountDownLatch(2);
        CountDownLatch start = new CountDownLatch(1);
        try (java.util.concurrent.ExecutorService executor = Executors.newVirtualThreadPerTaskExecutor()) {
            Future<Integer> first = executor.submit(() -> {
                ready.countDown();
                start.await(10, TimeUnit.SECONDS);
                return checkout(attendance, 1, code, true, UUID.randomUUID()).status();
            });
            Future<Integer> second = executor.submit(() -> {
                ready.countDown();
                start.await(10, TimeUnit.SECONDS);
                return checkout(attendance, 1, code, true, UUID.randomUUID()).status();
            });
            assertThat(ready.await(10, TimeUnit.SECONDS)).isTrue();
            start.countDown();
            assertThat(List.of(first.get(20, TimeUnit.SECONDS), second.get(20, TimeUnit.SECONDS)))
                .containsExactlyInAnyOrder(200, 409);
        }
        assertThat(db.fetchCount(CHILDREN_ATTENDANCE_EVENTS, CHILDREN_ATTENDANCE_EVENTS.TYPE.eq("COLLECTED")))
            .isEqualTo(1);
    }
}
