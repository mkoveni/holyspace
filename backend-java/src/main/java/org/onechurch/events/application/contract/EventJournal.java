package org.onechurch.events.application.contract;
import org.onechurch.events.domain.EventOccurrence;

import java.time.Instant;
import java.util.UUID;
public interface EventJournal {
    void request(EventJournalReader.Request request);
    void audit(UUID actor, String action, UUID target, String reason, Instant at);
    void occurrence(UUID actor, String action, EventOccurrence occurrence, String reason, Instant at);
    void registration(UUID actor, String action, UUID registration, String reason, Instant at);
}
