package org.onechurch.volunteering.application.query.listvolunteeractivities;

import java.time.Instant;

public record ListVolunteerActivitiesQuery(String kind, Instant from, Instant until, int limit, int offset) {
    public ListVolunteerActivitiesQuery(String kind, Instant from, Instant until) {
        this(kind, from, until, 100, 0);
    }
}
