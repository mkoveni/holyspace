package org.onechurch.volunteering.application.contract;

import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.Invitation;
import org.onechurch.volunteering.domain.Shift;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

public interface RosterReader {
    List<ServingActivityLocks.Activity> volunteerActivities(List<UUID> volunteers);
    List<ServingActivityLocks.Activity> teamActivities(UUID team);
    List<Shift> sourceShifts(String kind, UUID sourceId, int limit);
    Optional<Shift> shift(UUID id);
    Optional<Shift> shiftRequest(UUID request);
    List<Shift> shifts(Instant from, Instant until, UUID team, int limit, int offset);
    long shiftCount(Instant from, Instant until, UUID team);
    Optional<Assignment> assignment(UUID id);
    Optional<Assignment> assignmentRequest(UUID request);
    List<Assignment> assignments(UUID shift);
    List<Assignment> volunteerAssignments(UUID volunteer, Instant from, Instant until);
    Optional<Invitation> invitation(UUID id);
    List<Invitation> invitations(UUID assignment);
    List<Invitation> expirable(Instant at, int limit);
    List<Invitation> myInvitations(UUID volunteer, int limit, int offset);
    List<Event> events(UUID assignment, int limit, int offset);
    record Event(UUID id, String action, String reason, Instant occurredAt) {}
}
