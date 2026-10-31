package org.onechurch.events.application;
import org.onechurch.events.application.contract.EventReportReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventRules;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
import java.util.stream.Collectors;
public final class EventReports {
    public record Page(List<EventReportReader.Row> occurrences, EventReportReader.Totals totals) {}
    private final EventReportReader reader;
    private final Clock clock;
    public EventReports(EventReportReader reader, Clock clock) {
        this.reader = reader;
        this.clock = clock;
    }
    public Page report(UUID event, String search, Instant from, Instant until, int limit, int offset) {
        EventRules.range(from, until);
        EventRules.page(limit, offset);
        String term = EventRules.optional(search, 200);
        Instant now = clock.instant();
        return new Page(reader.rows(event, term, from, until, now, limit, offset),
            reader.totals(event, term, from, until, now));
    }
    public String export(UUID event, String search, Instant from, Instant until) {
        EventRules.range(from, until);
        String term = EventRules.optional(search, 200);
        Instant now = clock.instant();
        if (reader.totals(event, term, from, until, now).occurrences() > 10000) {
            throw EventException.invalid("Export at most 10,000 occurrences. Choose a shorter range.");
        }
        String rows = reader.rows(event, term, from, until, now, 10000, 0)
            .stream()
            .map(r -> List
                .of(cell(r.title()), cell(r.occurrenceId().toString()), cell(r.startsAt().toString()),
                    cell(r.endsAt().toString()), cell(r.timeZone()), cell(r.venue()), cell(r.status()),
                    Long.toString(r.counts().confirmed()), Long.toString(r.counts().waitlisted()),
                    Long.toString(r.counts().cancelled()), Long.toString(r.counts().attended()),
                    Long.toString(r.counts().absent()))
                .stream()
                .collect(Collectors.joining(",")))
            .collect(Collectors.joining("\r\n"));
        return "Event,Occurrence,Starts at,Ends at,Time zone,Venue,Status,Confirmed,Waitlisted,Cancelled,Attended,Absent\r\n"
            + rows + (rows.isEmpty() ? "" : "\r\n");
    }
    private String cell(String value) {
        String safe = value.stripLeading();
        if (!safe.isEmpty() && "=+-@".indexOf(safe.charAt(0)) >= 0) {
            value = "'" + value;
        }
        return "\"" + value.replace("\"", "\"\"") + "\"";
    }
}
