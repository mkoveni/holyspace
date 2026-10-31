package org.onechurch.childrenministry.sessions.application;

import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record LabelIssue(UUID attendanceId, int labelVersion, String pickupCode, Instant expiresAt,
    long attendanceVersion) {
    public LabelIssue {
        expiresAt = ChildrenRules.instant(expiresAt);
    }
    @Override
    public String toString() {
        return "LabelIssue[attendanceId=" + attendanceId + ", pickupCode=[REDACTED]]";
    }
}
