package org.onechurch.childrenministry.safeguarding.application.contract;
import java.time.Instant;
import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.UUID;
public interface SafeguardingFollowUpReader {
    record Child(UUID childId, UUID programmeId, String programme, UUID policyId, Instant guardianUntil,
        Instant consentUntil) {}
    record Pickup(UUID childId, UUID programmeId, UUID collectorId, Instant until) {}
    record Care(UUID childId, LocalDate reviewOn) {}
    record Facts(List<Child> children, List<Pickup> pickups, List<Care> care) {}
    Facts load(UUID programmeId, Collection<UUID> matchingIds, LocalDate on, Instant at, boolean includeCare);
}
