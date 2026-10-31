package org.onechurch.giving.application;
import org.onechurch.giving.application.contract.GivingLedgerReader;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.people.application.contract.PersonDirectory;

import java.math.BigInteger;
import java.time.Clock;
import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;
public final class GivingReports {
    private final GivingLedgerReader ledger;
    private final PersonDirectory people;
    private final Clock clock;
    public GivingReports(GivingLedgerReader ledger, PersonDirectory people, Clock clock) {
        this.ledger = ledger;
        this.people = people;
        this.clock = clock;
    }
    public GivingLedgerReader.Report report(GivingReader.Filter filter) {
        GivingFilters.validate(filter);
        List<GivingLedgerReader.Line> lines = ledger.lines(filter, 5000);
        Map<String, List<GivingLedgerReader.Line>> groups =
            lines.stream().collect(Collectors.groupingBy(GivingLedgerReader.Line::currency));
        List<GivingReader.Total> totals = groups.entrySet().stream().sorted(Map.Entry.comparingByKey()).map(entry -> {
            BigInteger gross = entry.getValue()
                .stream()
                .filter(l -> l.amountMinor() > 0)
                .map(l -> BigInteger.valueOf(l.amountMinor()))
                .reduce(BigInteger.ZERO, BigInteger::add);
            BigInteger reversed = entry.getValue()
                .stream()
                .filter(l -> l.amountMinor() < 0)
                .map(l -> BigInteger.valueOf(l.amountMinor()).negate())
                .reduce(BigInteger.ZERO, BigInteger::add);
            return new GivingReader.Total(entry.getKey(), gross.toString(), reversed.toString(),
                gross.subtract(reversed).toString());
        }).toList();
        String name = filter.donorId() == null ? "All donors"
            : people.get(filter.donorId()).map(PersonDirectory.Entry::name).orElse("Person unavailable");
        return new GivingLedgerReader.Report(filter.donorId(), name, filter.from(), filter.to(), clock.instant(), lines,
            totals);
    }
}
