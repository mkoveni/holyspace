package org.onechurch.people.application.query.getperson;

import static org.assertj.core.api.Assertions.assertThat;
import static org.mockito.Mockito.mock;
import static org.mockito.Mockito.when;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.domain.AgeCategory;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.PersonDetails;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

class GetPersonAgePolicyTest {
    @Test
    void defaultsToTheChurchLocalDateAtTheAdultBirthdayBoundary() {
        Clock clock = Clock.fixed(Instant.parse("2026-10-25T22:30:00Z"), ZoneOffset.UTC);
        UUID id = UUID.randomUUID();
        Person person = Person.register(id,
            new PersonDetails(null, "Child", "Mabasa", LocalDate.of(2008, 10, 26), null, null, null), null,
            LocalDate.of(2026, 10, 26), clock.instant());
        PersonReader reader = mock(PersonReader.class);
        when(reader.find(id)).thenReturn(Optional.of(person));
        when(reader.contacts(id)).thenReturn(List.of());
        when(reader.addresses(id)).thenReturn(List.of());
        GetPersonHandler handler =
            new GetPersonHandler(mock(PermissionChecker.class), reader, clock, ZoneId.of("Africa/Johannesburg"));
        assertThat(handler.handle(new GetPersonQuery(id, null)).ageCategory()).isEqualTo(AgeCategory.ADULT);
        assertThat(handler.handle(new GetPersonQuery(id, LocalDate.of(2026, 10, 25))).ageCategory())
            .isEqualTo(AgeCategory.CHILD);
    }
}
