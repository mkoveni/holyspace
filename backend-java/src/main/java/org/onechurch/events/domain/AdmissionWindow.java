package org.onechurch.events.domain;
import java.time.Instant;
public final class AdmissionWindow {
    private AdmissionWindow() {
    }
    public static boolean open(EventOccurrence occurrence, Instant now) {
        return occurrence.status().equals("SCHEDULED") && !now.isBefore(occurrence.startsAt().minusSeconds(7200))
            && now.isBefore(occurrence.endsAt());
    }
    public static void require(EventOccurrence occurrence, Instant now) {
        if (!open(occurrence, now)) {
            throw EventException
                .conflict("Check-in is available from two hours before a scheduled occurrence until its end.");
        }
    }
}
