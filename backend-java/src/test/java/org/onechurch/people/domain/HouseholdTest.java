package org.onechurch.people.domain;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.util.UUID;

class HouseholdTest {

    @Test
    void contactMustBeACurrentMemberOfTheSameHousehold() {
        Instant now = Instant.parse("2026-10-25T10:00:00Z");
        Household household = Household.create(UUID.randomUUID(), "Mabasa", now);
        HouseholdMembership member =
            new HouseholdMembership(UUID.randomUUID(), household.id(), UUID.randomUUID(), false, now, null);
        assertThat(household.contact(member, 0, now).primaryContactMembershipId()).isEqualTo(member.id());
        Household other = Household.create(UUID.randomUUID(), "Other", now);
        assertThatThrownBy(() -> other.contact(member, 0, now)).isInstanceOf(PeopleException.class);
        HouseholdMembership ended = member.end(now.plusSeconds(1));
        assertThatThrownBy(() -> household.contact(ended, 0, now.plusSeconds(1))).isInstanceOf(PeopleException.class);
        assertThat(ended.currentAt(now)).isTrue();
        assertThat(ended.currentAt(now.plusSeconds(1))).isFalse();
    }
}
