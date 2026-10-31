package org.onechurch.volunteering.application;

import org.onechurch.volunteering.application.contract.ActivitySource;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.VolunteerException;

import java.time.Instant;
import java.util.List;

public final class ActivityRegistry {
    private final List<ActivitySource> sources;
    public ActivityRegistry(List<ActivitySource> sources) {
        this.sources = List.copyOf(sources);
    }
    public void require(Shift shift) {
        if (shift.sourceKind().equals("STANDALONE")) {
            return;
        }
        String current = revision(shift);
        if (!current.equals(shift.sourceRevision())) {
            throw VolunteerException.conflict(
                "The linked activity was rescheduled. Review the duty and update the shift before proceeding.");
        }
    }
    public String revision(Shift shift) {
        if (shift.sourceKind().equals("STANDALONE")) {
            return null;
        }
        ActivitySource source = sources.stream()
            .filter(s -> s.kind().equals(shift.sourceKind()))
            .findFirst()
            .orElseThrow(
                () -> VolunteerException.invalid("This activity source is not available in the Java application."));
        ActivitySource.Activity activity = source.find(shift.sourceId())
            .filter(ActivitySource.Activity::active)
            .orElseThrow(() -> VolunteerException.conflict("The linked activity is cancelled, closed or unavailable."));
        return activity.scheduleRevision() == null
            ? Fingerprint.of(activity.id(), activity.startsAt(), activity.endsAt()) : Fingerprint.of(activity.id(),
                activity.startsAt(), activity.endsAt(), activity.scheduleRevision(), activity.venueLabel());
    }
    public List<ActivitySource.Activity> upcoming(String kind, Instant from, Instant until, int limit, int offset) {
        return sources.stream()
            .filter(s -> s.kind().equals(kind))
            .findFirst()
            .map(s -> s.upcoming(from, until, limit, offset))
            .orElse(List.of());
    }
    public List<ActivitySource.Activity> upcoming(String kind, Instant from, Instant until) {
        return sources.stream()
            .filter(s -> s.kind().equals(kind))
            .findFirst()
            .map(s -> s.upcoming(from, until))
            .orElse(List.of());
    }
}
