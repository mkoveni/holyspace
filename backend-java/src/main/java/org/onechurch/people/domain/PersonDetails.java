package org.onechurch.people.domain;

import java.time.LocalDate;

public record PersonDetails(String titleCode, String firstName, String lastName, LocalDate dateOfBirth, Gender gender,
    LifeStatus lifeStatus, LocalDate dateOfDeath) {

    public PersonDetails {
        titleCode = PeopleRules.titleCode(titleCode);
        firstName = PeopleRules.text(firstName, "First name", 100);
        lastName = PeopleRules.text(lastName, "Last name", 100);
        gender = gender == null ? Gender.UNKNOWN : gender;
        lifeStatus = lifeStatus == null ? LifeStatus.UNKNOWN : lifeStatus;
        if (dateOfDeath != null
            && (lifeStatus != LifeStatus.DECEASED || dateOfBirth != null && dateOfDeath.isBefore(dateOfBirth))) {
            throw PeopleException.invalid("Date of death must follow birth and requires deceased life status.");
        }
    }

    public void validateAt(LocalDate today) {
        if (dateOfBirth != null && dateOfBirth.isAfter(today) || dateOfDeath != null && dateOfDeath.isAfter(today)) {
            throw PeopleException.invalid("Birth and death dates cannot be in the future.");
        }
    }
}
