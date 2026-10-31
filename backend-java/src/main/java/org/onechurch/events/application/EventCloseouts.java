package org.onechurch.events.application;
import org.onechurch.events.application.contract.CloseoutReader;
import org.onechurch.events.application.contract.EventJournal;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.OccurrenceCloseout;
import org.onechurch.events.domain.repository.CloseoutRepository;
import org.onechurch.events.domain.repository.OccurrenceRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class EventCloseouts {
    private final OccurrenceRepository occurrences;
    private final CloseoutReader reader;
    private final CloseoutRepository repository;
    private final EventReadiness readiness;
    private final EventJournal journal;
    private final UnitOfWork tx;
    private final Clock clock;
    public EventCloseouts(OccurrenceRepository occurrences, CloseoutReader reader, CloseoutRepository repository,
        EventReadiness readiness, EventJournal journal, UnitOfWork tx, Clock clock) {
        this.occurrences = occurrences;
        this.reader = reader;
        this.repository = repository;
        this.readiness = readiness;
        this.journal = journal;
        this.tx = tx;
        this.clock = clock;
    }
    public void close(UUID actor, UUID occurrence, long expected, String hash, String note, String followUp,
        boolean attendanceReviewed, boolean followUpReviewed) {
        tx.execute(() -> {
            occurrences.lockOccurrence(occurrence).orElseThrow(() -> EventException.missing("Occurrence"));
            EventReadiness.Review review = readiness.review(occurrence);
            if (!review.notices().complete()) {
                throw EventException
                    .conflict("The notice summary is incomplete. This occurrence needs a separate review.");
            }
            EventPlanning.requireHash(review.hash(), hash);
            OccurrenceCloseout current =
                reader.closeout(occurrence).orElseGet(() -> OccurrenceCloseout.open(occurrence));
            OccurrenceCloseout next =
                current.close(review.occurrence(), expected, hash, note, followUp, attendanceReviewed, followUpReviewed,
                    review.attendance().waitlisted() > 0 || review.notices().unresolved() > 0, actor, clock.instant());
            persist(next, review);
            return null;
        });
    }
    public void reopen(UUID actor, UUID occurrence, long expected, String reason) {
        tx.execute(() -> {
            occurrences.lockOccurrence(occurrence).orElseThrow(() -> EventException.missing("Occurrence"));
            OccurrenceCloseout current =
                reader.closeout(occurrence).orElseGet(() -> OccurrenceCloseout.open(occurrence));
            persist(current.reopen(expected, reason, actor, clock.instant()), readiness.review(occurrence));
            return null;
        });
    }
    private void persist(OccurrenceCloseout next, EventReadiness.Review review) {
        repository.save(next, review.attendance().attended(), review.attendance().absent(),
            review.attendance().waitlisted(), review.notices().unresolved());
        journal.audit(next.updatedBy(), "occurrence.closeout." + next.status().toLowerCase(java.util.Locale.ROOT),
            next.occurrenceId(), next.note(), next.updatedAt());
    }
}
