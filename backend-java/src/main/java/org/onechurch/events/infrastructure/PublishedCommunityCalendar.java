package org.onechurch.events.infrastructure;
import org.onechurch.events.application.EventPlanning;
import org.onechurch.events.application.ScheduleReview;
import org.onechurch.events.application.contract.CommunityCalendar;
import org.onechurch.events.application.contract.CommunityScheduleReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.springframework.stereotype.Component;

import java.time.ZoneId;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
import java.util.function.Supplier;
@Component
public class PublishedCommunityCalendar implements CommunityCalendar, CommunityScheduleReader {
    private final ScheduleReview reviews;
    private final EventPlanning planning;
    private final OccurrenceReader reader;
    private final OccurrenceRepository repository;
    private final PermissionChecker auth;
    public PublishedCommunityCalendar(ScheduleReview reviews, EventPlanning planning, OccurrenceReader reader,
        OccurrenceRepository repository, PermissionChecker auth) {
        this.reviews = reviews;
        this.planning = planning;
        this.reader = reader;
        this.repository = repository;
        this.auth = auth;
    }
    private ScheduleReview.Draft draft(Schedule s) {
        if (s == null) {
            throw EventException.invalid("Choose a meeting schedule.");
        }
        return new ScheduleReview.Draft(s.venueId(), s.timeZone(), s.frequency(), s.interval(), s.count(),
            s.firstDate(), s.startsAt(), s.endsAt(), s.endDayOffset());
    }
    private Date date(EventOccurrence o) {
        return new Date(o.id(), o.startsAt(), o.endsAt(), o.timeZone(), o.venueName(), o.status(),
            o.scheduleRevision());
    }
    private <T> T translate(Supplier<T> action) {
        try {
            return action.get();
        } catch (EventException e) {
            throw new Failure(switch (e.kind()) {
                case INVALID -> 400;
                case NOT_FOUND -> 404;
                case CONFLICT -> 409;
            }, e.getMessage());
        }
    }
    public Preview preview(Schedule s) {
        return translate(() -> previewSchedule(s));
    }
    private Preview previewSchedule(Schedule s) {
        auth.requirePermission("events.manage");
        ScheduleReview.Preview p = reviews.preview(draft(s));
        return new Preview(p.hash(),
            p.dates()
                .stream()
                .map(w -> new Date(null, w.startsAt(), w.endsAt(), s.timeZone(), p.venue().name(), "SCHEDULED", 0))
                .toList());
    }
    public List<Date> create(UUID actor, UUID request, String name, Schedule schedule, String hash) {
        return translate(() -> createSchedule(actor, request, name, schedule, hash));
    }
    private List<Date> createSchedule(UUID actor, UUID request, String name, Schedule schedule, String hash) {
        auth.requirePermission(actor, "events.manage");
        UUID event = planning.create(actor, request, name, "MEETING", "Group gathering", draft(schedule), hash);
        return reader.calendar(event, "",
            schedule.firstDate().atStartOfDay(ZoneId.of(schedule.timeZone())).toInstant().minusSeconds(86400),
            schedule.firstDate().plusYears(3).atStartOfDay(ZoneId.of(schedule.timeZone())).toInstant(), null, 105, 0)
            .stream()
            .map(this::date)
            .toList();
    }
    public List<Date> dates(List<UUID> ids) {
        return reader.byIds(ids).stream().map(this::date).toList();
    }
    public Optional<Date> get(UUID id) {
        return reader.occurrence(id).map(this::date);
    }
    public Optional<Date> lock(UUID id) {
        return repository.lockOccurrence(id).map(this::date);
    }
}
