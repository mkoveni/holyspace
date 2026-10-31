package org.onechurch.events.application;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.application.contract.CloseoutReader;
import org.onechurch.events.application.contract.EventReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.RegistrationReader;
import org.onechurch.events.application.contract.VenueReader;
import org.onechurch.events.domain.EventDefinition;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.OccurrenceCloseout;
import org.onechurch.events.domain.RegistrationPolicy;
import org.onechurch.identityaccess.application.contract.AccountNames;
import org.onechurch.volunteering.application.contract.ActivityStaffing;

import java.time.Clock;
import java.time.Instant;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.UUID;
public final class EventReadiness {
    public record Check(String key, String title, String status, String detail) {}
    public record Notices(long approved, long submitted, long delivered, long pending, long failed, long captured,
        long cancelled, long remindersApproved, boolean complete) {
        public long unresolved() {
            return pending + failed + captured;
        }
    }
    public record Review(EventOccurrence occurrence, AttendanceReader.Counts attendance, Notices notices, String hash,
        boolean canClose) {}
    public record View(List<Check> checks, ActivityStaffing.Summary staffing, Review review,
        OccurrenceCloseout closeout, boolean changedSinceCloseout, Instant assessedAt) {}
    private final OccurrenceReader occurrences;
    private final EventReader events;
    private final VenueReader venues;
    private final RegistrationReader registrations;
    private final AttendanceReader attendance;
    private final CloseoutReader closeouts;
    private final DeliveryReader deliveries;
    private final ActivityStaffing staffing;
    private final Clock clock;
    private final AccountNames accounts;
    public EventReadiness(OccurrenceReader occurrences, EventReader events, VenueReader venues,
        RegistrationReader registrations, AttendanceReader attendance, CloseoutReader closeouts,
        DeliveryReader deliveries, ActivityStaffing staffing, Clock clock, AccountNames accounts) {
        this.occurrences = occurrences;
        this.events = events;
        this.venues = venues;
        this.registrations = registrations;
        this.attendance = attendance;
        this.closeouts = closeouts;
        this.deliveries = deliveries;
        this.staffing = staffing;
        this.clock = clock;
        this.accounts = accounts;
    }
    public Review review(UUID id) {
        EventOccurrence o = occurrences.occurrence(id).orElseThrow(() -> EventException.missing("Occurrence"));
        RegistrationPolicy policy = registrations.policy(id).orElseThrow();
        EventDefinition event = events.event(o.eventId()).orElseThrow();
        AttendanceReader.Counts counts = attendance.counts(id, clock.instant());
        List<CloseoutReader.Notice> messages = closeouts.notices(id, 10001);
        boolean complete = messages.size() <= 10000;
        long submitted = 0, delivered = 0, pending = 0, failed = 0, captured = 0, cancelled = 0;
        List<String> revisions = new ArrayList<>();
        for (int offset = 0; offset < Math.min(messages.size(), 10000); offset += 500) {
            List<CloseoutReader.Notice> batch =
                messages.subList(offset, Math.min(offset + 500, Math.min(messages.size(), 10000)));
            Map<UUID, DeliveryReader.Summary> summaries =
                deliveries.summaries(batch.stream().map(CloseoutReader.Notice::id).toList());
            for (CloseoutReader.Notice message : batch) {
                DeliveryReader.Summary result = summaries.get(message.id());
                if (result == null) {
                    failed++;
                    revisions.add(message.id() + ":MISSING");
                } else {
                    revisions
                        .add(message.id() + ":" + result.version() + ":" + result.status() + ":" + result.captured());
                    if (result.captured()) {
                        captured++;
                    } else {
                        switch (result.status()) {
                            case "SENT" -> submitted++;
                            case "DELIVERED" -> delivered++;
                            case "CANCELLED" -> cancelled++;
                            case "QUEUED", "SCHEDULED", "SENDING" -> pending++;
                            default -> failed++;
                        }
                    }
                }
            }
        }
        Notices notices = new Notices(messages.size(), submitted, delivered, pending, failed, captured, cancelled,
            closeouts.currentReminderBookings(id), complete);
        String hash = Fingerprint.of(o.version(), event.version(), policy.version(), counts,
            closeouts.attendanceRevision(id), notices, revisions);
        return new Review(o, counts, notices, hash,
            complete && (o.status().equals("CANCELLED") || !clock.instant().isBefore(o.endsAt())));
    }
    public View view(UUID id) {
        Review review = review(id);
        EventOccurrence occurrence = review.occurrence();
        RegistrationPolicy policy = registrations.policy(id).orElseThrow();
        ActivityStaffing.Summary team = staffing.summarize("EVENT_OCCURRENCE", id);
        OccurrenceCloseout closeout = closeouts.closeout(id).orElseGet(() -> OccurrenceCloseout.open(id));
        boolean activeVenue = venues.venue(occurrence.venueId()).map(v -> v.active()).orElse(false);
        List<Check> checks = List.of(
            new Check("venue", "Venue", activeVenue ? "READY" : "REVIEW",
                occurrence.venueName() + (activeVenue ? " · Venue available" : " · Venue is archived")),
            new Check("registration", "Registration and capacity", policy.open() ? "READY" : "REVIEW",
                (policy.open() ? "Open" : "Closed") + " · " + review.attendance().confirmed() + " confirmed · "
                    + (policy.capacity() == null ? "No seat limit" : policy.capacity() + " places") + " · "
                    + review.attendance().waitlisted() + " waitlisted"),
            new Check("staffing", "Team coverage and readiness",
                team.complete() && team.shifts() > 0 && team.gaps() == 0 ? "READY" : "REVIEW",
                !team.complete() ? "More than 200 duties: review the team schedule directly."
                    : team.shifts() == 0 ? "No duties planned. Confirm whether staffing is needed."
                        : team.ready() + " ready of " + team.positions() + " positions · " + team.proposed()
                            + " proposed · " + team.gaps() + " gaps"),
            new Check("reminders", "Participant reminders",
                review.notices().remindersApproved() >= review.attendance().confirmed() ? "READY" : "REVIEW",
                review.notices().remindersApproved() + " of " + review.attendance().confirmed()
                    + " confirmed bookings have a reminder approved for the current schedule. Approval is not delivery."),
            new Check("deliveries", "Email follow-up",
                review.notices().complete() && review.notices().unresolved() == 0 ? "READY" : "REVIEW",
                review.notices().pending() + " pending · " + review.notices().failed() + " need attention · "
                    + review.notices().captured() + " captured locally"
                    + (review.notices().complete() ? "" : " · More than 10,000 notices: summary incomplete")));
        return new View(checks, team, review, closeout,
            closeout.status().equals("CLOSED") && !closeout.reviewHash().equals(review.hash()), clock.instant());
    }
    public List<CloseoutReader.History> history(UUID id, int limit, int offset) {
        EventRules.page(limit, offset);
        occurrences.occurrence(id).orElseThrow(() -> EventException.missing("Occurrence"));
        List<CloseoutReader.History> history = closeouts.history(id, limit, offset);
        Map<UUID, String> names =
            accounts.names(history.stream().map(CloseoutReader.History::actorId).distinct().toList());
        return history.stream()
            .map(h -> new CloseoutReader.History(h.id(), h.version(), h.action(), h.note(), h.followUp(), h.attended(),
                h.absent(), h.waitlisted(), h.unresolvedNotices(), h.actorId(),
                names.getOrDefault(h.actorId(), "Former coordinator"), h.occurredAt()))
            .toList();
    }
}
