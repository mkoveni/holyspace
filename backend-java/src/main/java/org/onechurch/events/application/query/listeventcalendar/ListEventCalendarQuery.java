package org.onechurch.events.application.query.listeventcalendar;
import java.time.Instant;
import java.util.UUID;
public record ListEventCalendarQuery(UUID eventId, String search, Instant from, Instant until, String status, int limit,
    int offset) {}
