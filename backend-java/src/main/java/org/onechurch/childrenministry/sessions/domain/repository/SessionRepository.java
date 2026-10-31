package org.onechurch.childrenministry.sessions.domain.repository;

import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStaff;
import org.onechurch.childrenministry.sessions.domain.Worker;

import java.util.Optional;
import java.util.UUID;
public interface SessionRepository {
    Optional<Location> locationForUpdate(UUID id);
    void insertLocation(Location value);
    Optional<Room> roomForUpdate(UUID id);
    void insertRoom(Room value);
    Optional<Worker> workerForUpdate(UUID id);
    void insertWorker(Worker value);
    void updateWorker(Worker value);
    Optional<MinistrySession> ministrySessionForUpdate(UUID id);
    void insertMinistrySession(MinistrySession value);
    void updateMinistrySession(MinistrySession value);
    void insertSessionStaff(SessionStaff value);

}
