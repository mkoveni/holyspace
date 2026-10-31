package org.onechurch.volunteering.domain.repository;

import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.Invitation;
import org.onechurch.volunteering.domain.Shift;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;

public interface RosterRepository {
    Optional<Shift> lockShift(UUID id);
    Optional<Assignment> lockAssignment(UUID id);
    Optional<Invitation> lockInvitation(UUID id);
    void insertShift(Shift shift);
    void saveShift(Shift shift);
    void insertAssignment(Assignment assignment);
    void saveAssignment(Assignment assignment);
    void insertInvitation(Invitation invitation);
    void saveInvitation(Invitation invitation);
    void event(UUID assignment, UUID actor, String action, String reason, Instant at);
}
