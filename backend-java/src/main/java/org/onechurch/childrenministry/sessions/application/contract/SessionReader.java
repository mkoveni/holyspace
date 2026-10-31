package org.onechurch.childrenministry.sessions.application.contract;

import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.Worker;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface SessionReader {
    List<MinistrySession> stationSessions(UUID accountId, Instant from, Instant until, int limit, int offset);
    boolean presentElsewhere(UUID workerId, UUID sessionId, Instant from, Instant until);
    List<Location> locations(int limit, int offset);
    Optional<Location> location(UUID id);
    Optional<Room> room(UUID id);
    Optional<Worker> worker(UUID id);
    Optional<MinistrySession> ministrySession(UUID id);
    List<MinistrySession> sessions(UUID locationId, Instant from, Instant until, int limit, int offset);
    List<SessionStaff> staff(UUID sessionId);
    Optional<Worker> workerByAccount(UUID accountId);
    List<Room> rooms(UUID locationId);
}
