package org.onechurch.giving.application;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
import org.onechurch.giving.domain.Money;

import java.time.temporal.ChronoUnit;
public final class GivingFilters {
    private GivingFilters() {
    }
    public static void validate(GivingReader.Filter filter) {
        GivingRules.page(filter.limit(), filter.offset());
        if (filter.from() == null || filter.to() == null || filter.to().isBefore(filter.from())
            || filter.from().getYear() < 1900 || filter.to().getYear() > 9999
            || ChronoUnit.DAYS.between(filter.from(), filter.to()) > 3660) {
            throw GivingException.invalid("Choose a period of at most ten years, with the end on or after the start.");
        }
        if (filter.currency() != null && !Money.CURRENCIES.contains(filter.currency())) {
            throw GivingException.invalid("Choose a supported currency.");
        }
    }
}
