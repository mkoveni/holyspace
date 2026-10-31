package org.onechurch.people.application.query.searchpeople;

import org.onechurch.people.domain.AgeCategory;
import org.onechurch.people.domain.MembershipType;

import java.time.LocalDate;

public record SearchPeopleQuery(String search, boolean includeArchived, MembershipType membershipType,
    AgeCategory ageCategory, LocalDate asOf, int limit, int offset) {}
