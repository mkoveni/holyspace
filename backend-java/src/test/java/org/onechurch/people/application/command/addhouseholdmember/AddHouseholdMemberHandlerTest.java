package org.onechurch.people.application.command.addhouseholdmember;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_HOUSEHOLD_MEMBERSHIPS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.dao.DataIntegrityViolationException;

import java.sql.SQLException;
import java.util.List;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;

class AddHouseholdMemberHandlerTest extends PeopleIntegrationTestSupport {
    @Autowired private AddHouseholdMemberHandler handler;
    @Test
    void onlyOneOfTwoConcurrentPrimaryHouseholdAssignmentsCanSucceed() throws Exception {
        UUID person = person("Child");
        UUID a = household("A");
        UUID b = household("B");
        CountDownLatch start = new CountDownLatch(1);
        try (ExecutorService pool = Executors.newFixedThreadPool(2)) {
            Future<Boolean> first = pool.submit(() -> attempt(a, person, start));
            Future<Boolean> second = pool.submit(() -> attempt(b, person, start));
            start.countDown();
            assertThat(List.of(first.get(15, TimeUnit.SECONDS), second.get(15, TimeUnit.SECONDS)))
                .containsExactlyInAnyOrder(true, false);
        }
        assertThat(db.fetchCount(PEOPLE_HOUSEHOLD_MEMBERSHIPS)).isEqualTo(1);
    }
    private boolean attempt(UUID household, UUID person, CountDownLatch start) throws Exception {
        start.await(10, TimeUnit.SECONDS);
        try {
            asActor(admin, () -> handler.handle(new AddHouseholdMemberCommand(adminId, household, 0, person, true)));
            return true;
        } catch (DataIntegrityViolationException error) {
            assertThat(error.getMostSpecificCause()).isInstanceOfSatisfying(SQLException.class,
                sql -> assertThat(sql.getSQLState()).isEqualTo("23P01"));
            return false;
        }
    }
}
