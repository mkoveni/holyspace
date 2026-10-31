package org.onechurch.people.domain;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.util.UUID;

class ContactMethodTest {

    @Test
    void normalizesEmailsWithoutDeduplicatingAcrossPeople() {
        ContactMethod contact = new ContactMethod(UUID.randomUUID(), UUID.randomUUID(), ContactMethod.Kind.EMAIL,
            " Family@Church.org ", true, Instant.now(), null);
        assertThat(contact.value()).isEqualTo("family@church.org");
        assertThatThrownBy(() -> new ContactMethod(UUID.randomUUID(), UUID.randomUUID(), ContactMethod.Kind.EMAIL,
            "bad", true, Instant.now(), null)).isInstanceOf(PeopleException.class);
        assertThatThrownBy(() -> new ContactMethod(UUID.randomUUID(), UUID.randomUUID(), ContactMethod.Kind.EMAIL,
            "a b@church.org", true, Instant.now(), null)).isInstanceOf(PeopleException.class);
    }
}
