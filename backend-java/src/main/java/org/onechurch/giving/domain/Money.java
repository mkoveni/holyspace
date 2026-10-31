package org.onechurch.giving.domain;
import java.util.List;
public record Money(long amountMinor, String currency) {
    public static final List<String> CURRENCIES = List.of("ZAR", "USD", "EUR", "GBP");
    public Money {
        if (amountMinor < 1 || amountMinor > 1_000_000_000_000L) {
            throw GivingException.invalid("Enter a positive amount of at most 10,000,000,000.00.");
        }
        if (!CURRENCIES.contains(currency)) {
            throw GivingException
                .invalid("Choose ZAR, USD, EUR or GBP. All supported currencies use two decimal places.");
        }
    }
}
