package org.onechurch.childrenministry.sessions.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.Objects;
import java.util.UUID;
public record Attendance(UUID id, UUID childId, UUID sessionId, Instant arrivedAt, UUID arrivedBy,
    UUID arrivalRequestId, Instant checkedOutAt, UUID checkedOutBy, UUID collectorId, UUID checkoutRequestId,
    UUID authorizationId, boolean identityVerified, String exceptionReason, String labelHash, int labelVersion,
    Instant labelExpiresAt, int failedAttempts, Instant attemptsResetAt, long version) {
    public Attendance {
        arrivedAt = ChildrenRules.instant(arrivedAt);
        checkedOutAt = ChildrenRules.instant(checkedOutAt);
        labelExpiresAt = ChildrenRules.instant(labelExpiresAt);
        attemptsResetAt = ChildrenRules.instant(attemptsResetAt);
        Objects.requireNonNull(id);
        Objects.requireNonNull(childId);
        Objects.requireNonNull(sessionId);
        Objects.requireNonNull(arrivedAt);
    }

    public void requireOpen() {
        if (checkedOutAt != null) {
            throw ChildrenException.conflict("Child has already been collected.");
        }
    }
    public Attendance issueLabel(String hash, Instant expires, long expected, String reason) {
        requireOpen();
        ChildrenRules.version(version, expected);
        if (labelVersion > 0) {
            ChildrenRules.text(reason, 500);
        }
        return new Attendance(id, childId, sessionId, arrivedAt, arrivedBy, arrivalRequestId, checkedOutAt,
            checkedOutBy, collectorId, checkoutRequestId, authorizationId, identityVerified, exceptionReason, hash,
            labelVersion + 1, expires, failedAttempts, attemptsResetAt, version + 1);
    }
    public Attendance failedAttempt(Instant now) {
        boolean reset = attemptsResetAt == null || !now.isBefore(attemptsResetAt);
        return new Attendance(id, childId, sessionId, arrivedAt, arrivedBy, arrivalRequestId, checkedOutAt,
            checkedOutBy, collectorId, checkoutRequestId, authorizationId, identityVerified, exceptionReason, labelHash,
            labelVersion, labelExpiresAt, reset ? 1 : failedAttempts + 1,
            reset ? now.plusSeconds(900) : attemptsResetAt, version + 1);
    }
    public boolean throttled(Instant now) {
        return failedAttempts >= 5 && attemptsResetAt != null && now.isBefore(attemptsResetAt);
    }
    public Attendance collect(UUID collector, UUID operator, UUID requestId, UUID authorization, boolean verified,
        String reason, long expected, Instant now) {
        requireOpen();
        ChildrenRules.version(version, expected);
        if (!verified) {
            throw ChildrenException.invalid("Confirm collector identity before handover.");
        }
        if (now.isBefore(arrivedAt)) {
            throw ChildrenException.invalid("Handover cannot predate arrival.");
        }
        return new Attendance(id, childId, sessionId, arrivedAt, arrivedBy, arrivalRequestId, now, operator, collector,
            requestId, authorization, true, ChildrenRules.optional(reason, 500), null, labelVersion, labelExpiresAt,
            failedAttempts, attemptsResetAt, version + 1);
    }
    public Attendance transfer(UUID targetSession, long expected) {
        requireOpen();
        ChildrenRules.version(version, expected);
        if (sessionId.equals(targetSession)) {
            throw ChildrenException.invalid("Select a different target session.");
        }
        return new Attendance(id, childId, targetSession, arrivedAt, arrivedBy, arrivalRequestId, checkedOutAt,
            checkedOutBy, collectorId, checkoutRequestId, authorizationId, identityVerified, exceptionReason, null,
            labelVersion + 1, null, failedAttempts, attemptsResetAt, version + 1);
    }
    @Override
    public String toString() {
        return "Attendance[id=" + id + ", version=" + version + "]";
    }
}
