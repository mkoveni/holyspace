package org.onechurch.childrenministry.reporting.application;
import org.onechurch.childrenministry.reporting.application.contract.AttendanceReportReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.ZoneId;
import java.time.temporal.ChronoUnit;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
public final class AttendanceReporting {
    private final AttendanceReportReader reader;
    private final PersonDirectory people;
    private final ZoneId zone;
    private final Clock clock;
    public AttendanceReporting(AttendanceReportReader reader, PersonDirectory people, ZoneId zone, Clock clock) {
        this.reader = reader;
        this.people = people;
        this.zone = zone;
        this.clock = clock;
    }
    public AttendanceReport report(AttendanceReportFilter filter, int limit, int offset) {
        ChildrenRules.page(limit, offset);
        return named(reader.read(criteria(filter), limit, offset));
    }
    public AttendanceReport export(AttendanceReportFilter filter) {
        AttendanceReport report = reader.read(criteria(filter), 10001, 0);
        if (report.visits().size() > 10000) {
            throw ChildrenException.invalid("This export exceeds 10,000 visits. Narrow the dates or filters.");
        }
        return named(report);
    }
    public AttendanceReport timeline(UUID attendanceId, int limit, int offset) {
        ChildrenRules.page(limit, offset);
        AttendanceReport report =
            reader.read(new AttendanceReportReader.Criteria(null, null, null, null, null, null, "ALL", attendanceId),
                limit, offset);
        if (report.summary().visits() == 0) {
            throw ChildrenException.missing("Attendance");
        }
        return named(report);
    }
    private AttendanceReportReader.Criteria criteria(AttendanceReportFilter filter) {
        if (filter.from() == null || filter.until() == null || filter.until().isBefore(filter.from())
            || ChronoUnit.DAYS.between(filter.from(), filter.until()) > 365) {
            throw ChildrenException.invalid("Choose a date range of no more than 366 days.");
        }
        String outcome = filter.outcome() == null ? "ALL" : filter.outcome();
        if (!List.of("ALL", "ATTENDING", "COLLECTED", "TRANSFERRED", "SUPERVISOR").contains(outcome)) {
            throw ChildrenException.invalid("Choose a valid attendance outcome.");
        }
        String search = ChildrenRules.optional(filter.search(), 120);
        return new AttendanceReportReader.Criteria(filter.from().atStartOfDay(zone).toInstant(),
            filter.until().plusDays(1).atStartOfDay(zone).toInstant(), filter.programmeId(), filter.locationId(),
            filter.childId(), search.isEmpty() ? null : people.matchingIds(search), outcome, null);
    }
    private AttendanceReport named(AttendanceReport report) {
        List<UUID> ids = report.visits().stream().map(AttendanceReport.Visit::childId).distinct().toList();
        Map<UUID, PersonDirectory.Entry> names =
            people.getAll(ids).stream().collect(Collectors.toMap(PersonDirectory.Entry::id, Function.identity()));
        return new AttendanceReport(report.visits()
            .stream()
            .map(visit -> visit
                .named(names.containsKey(visit.childId()) ? names.get(visit.childId()).name() : "Unavailable person"))
            .toList(), report.summary(), zone.getId(), clock.instant());
    }
}
