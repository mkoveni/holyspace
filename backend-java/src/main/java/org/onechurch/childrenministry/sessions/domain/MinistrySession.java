package org.onechurch.childrenministry.sessions.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record MinistrySession(UUID id, UUID classId, UUID roomId, Instant startsAt, Instant endsAt, int seatLimit,
    SessionStatus status, long version) {
    public MinistrySession {
        startsAt = ChildrenRules.instant(startsAt);
        endsAt = ChildrenRules.instant(endsAt);
        ChildrenRules.period(startsAt, endsAt);
        if (seatLimit < 1 || status == null) {
            throw ChildrenException.invalid("Invalid session details.");
        }
    }

    public MinistrySession status(SessionStatus next, long expected) {
        ChildrenRules.version(version, expected);
        if (next == null || (status == SessionStatus.CLOSED || status == SessionStatus.CANCELLED) && next != status) {
            throw ChildrenException.conflict("This session is terminal and cannot be reopened.");
        }
        if (next == SessionStatus.PLANNED && status != SessionStatus.PLANNED) {
            throw ChildrenException.conflict("An operational session cannot return to planned.");
        }
        return new MinistrySession(id, classId, roomId, startsAt, endsAt, seatLimit, next, version + 1);
    }
    public void requireArrivalWindow(Instant now) {
        if (status != SessionStatus.OPEN || now.isBefore(startsAt.minusSeconds(1800)) || !now.isBefore(endsAt)) {
            throw ChildrenException
                .conflict("Arrivals require an open session, from 30 minutes before its start until its end.");
        }
    }
}
