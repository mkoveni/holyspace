package org.onechurch.childrenministry.safeguarding.application;
import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public record SafeguardingFollowUpView(List<Issue> issues, long total, Summary summary, boolean careIncluded,
    LocalDate through, String timeZone, Instant checkedAt) {
    public record Issue(String id, String kind, String state, UUID childId, String childName, UUID programmeId,
        String programme, Instant expiresAt, LocalDate reviewOn, String action, String message) {}
    public record Summary(long children, long missing, long expired, long expiring, long careDue) {}
}
