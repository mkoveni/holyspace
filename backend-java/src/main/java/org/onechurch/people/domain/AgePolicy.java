package org.onechurch.people.domain;

import java.time.LocalDate;

public final class AgePolicy {
    public static final int ADULT_AGE = 18;
    private AgePolicy() {
    }
    /** A leap-day birthday falls on February 28 in a non-leap year, matching LocalDate.plusYears. */
    public static LocalDate adultBirthDateCutoff(LocalDate asOf) {
        LocalDate cutoff = asOf.minusYears(ADULT_AGE);
        if (asOf.getMonthValue() == 2 && asOf.getDayOfMonth() == 28 && !asOf.isLeapYear() && cutoff.isLeapYear()) {
            return cutoff.plusDays(1);
        }
        return cutoff;
    }
    public static AgeCategory category(LocalDate birth, LocalDate asOf) {
        if (birth == null || birth.isAfter(asOf)) {
            return AgeCategory.UNKNOWN;
        }
        return birth.isAfter(adultBirthDateCutoff(asOf)) ? AgeCategory.CHILD : AgeCategory.ADULT;
    }
}
