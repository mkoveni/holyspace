package org.onechurch.events.infrastructure.persistence;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRegistration;
import org.onechurch.events.domain.EventSeries;
import org.onechurch.events.domain.RegistrationPolicy;
import org.onechurch.events.domain.Venue;
import org.onechurch.shared.infrastructure.jooq.tables.records.EventDefinitionsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.EventOccurrencesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.EventRegistrationPoliciesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.EventRegistrationsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.EventSeriesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.EventVenuesRecord;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
final class EventRecords {
    private EventRecords() {
    }
    static OffsetDateTime time(Instant at) {
        return at == null ? null : at.atOffset(ZoneOffset.UTC);
    }
    static EventDefinition event(EventDefinitionsRecord r) {
        return new EventDefinition(r.getId(), r.getTitle(), r.getType(), r.getDescription(), r.getActive(),
            r.getVersion());
    }
    static EventSeries series(EventSeriesRecord r) {
        return new EventSeries(r.getId(), r.getEventId(), r.getTimeZone(), r.getFrequency(), r.getRepeatInterval(),
            r.getOccurrenceCount(), r.getFirstDate(), r.getStartsAt(), r.getEndsAt(), r.getEndDayOffset(),
            r.getVenueId(), r.getVersion());
    }
    static Venue venue(EventVenuesRecord r) {
        return new Venue(r.getId(), r.getName(), r.getAddress(), r.getExclusive(), r.getActive(), r.getVersion());
    }
    static EventOccurrence occurrence(EventOccurrencesRecord r) {
        return new EventOccurrence(r.getId(), r.getEventId(), r.getSeriesId(), r.getOriginDate(), r.getTimeZone(),
            r.getStartsAt().toInstant(), r.getEndsAt().toInstant(), r.getVenueId(), r.getVenueName(),
            r.getVenueAddress(), r.getExclusive(), r.getStatus(), r.getOverridden(), r.getVersion(),
            r.getScheduleRevision());
    }
    static RegistrationPolicy policy(EventRegistrationPoliciesRecord r) {
        return new RegistrationPolicy(r.getOccurrenceId(), r.getOpen(), r.getCapacity(), r.getWaitlistEnabled(),
            r.getNextPosition(), r.getVersion());
    }
    static EventRegistration registration(EventRegistrationsRecord r) {
        return new EventRegistration(r.getId(), r.getOccurrenceId(), r.getPersonId(), r.getStatus(),
            r.getQueuePosition(), r.getVersion(), r.getCreatedAt().toInstant(), r.getUpdatedAt().toInstant());
    }
}
