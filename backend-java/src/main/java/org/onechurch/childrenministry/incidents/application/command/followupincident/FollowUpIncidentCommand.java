package org.onechurch.childrenministry.incidents.application.command.followupincident;
import java.time.LocalDate;
import java.util.UUID;
public record FollowUpIncidentCommand(UUID actorId, UUID incidentId, long expectedVersion, String action, UUID ownerId,
    LocalDate reviewOn, UUID guardianAuthorityId, String note) {
    @Override
    public String toString() {
        return "FollowUpIncidentCommand[restricted]";
    }
}
