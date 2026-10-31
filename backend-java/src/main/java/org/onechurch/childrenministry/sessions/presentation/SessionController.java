package org.onechurch.childrenministry.sessions.presentation;
import org.onechurch.childrenministry.participation.application.command.createlocation.CreateLocationCommand;
import org.onechurch.childrenministry.participation.application.command.createlocation.CreateLocationHandler;
import org.onechurch.childrenministry.participation.application.command.createroom.CreateRoomCommand;
import org.onechurch.childrenministry.participation.application.command.createroom.CreateRoomHandler;
import org.onechurch.childrenministry.participation.application.query.listlocations.ListLocationsHandler;
import org.onechurch.childrenministry.participation.application.query.listlocations.ListLocationsQuery;
import org.onechurch.childrenministry.participation.application.query.listrooms.ListRoomsHandler;
import org.onechurch.childrenministry.participation.application.query.listrooms.ListRoomsQuery;
import org.onechurch.childrenministry.participation.domain.Location;
import org.onechurch.childrenministry.participation.domain.Room;
import org.onechurch.childrenministry.sessions.application.SessionView;
import org.onechurch.childrenministry.sessions.application.command.createsession.CreateSessionCommand;
import org.onechurch.childrenministry.sessions.application.command.createsession.CreateSessionHandler;
import org.onechurch.childrenministry.sessions.application.command.registerworker.RegisterWorkerCommand;
import org.onechurch.childrenministry.sessions.application.command.registerworker.RegisterWorkerHandler;
import org.onechurch.childrenministry.sessions.application.command.reviewworker.ReviewWorkerCommand;
import org.onechurch.childrenministry.sessions.application.command.reviewworker.ReviewWorkerHandler;
import org.onechurch.childrenministry.sessions.application.command.setsessionstaff.SetSessionStaffCommand;
import org.onechurch.childrenministry.sessions.application.command.setsessionstaff.SetSessionStaffHandler;
import org.onechurch.childrenministry.sessions.application.command.setsessionstatus.SetSessionStatusCommand;
import org.onechurch.childrenministry.sessions.application.command.setsessionstatus.SetSessionStatusHandler;
import org.onechurch.childrenministry.sessions.application.query.getsession.GetSessionHandler;
import org.onechurch.childrenministry.sessions.application.query.getsession.GetSessionQuery;
import org.onechurch.childrenministry.sessions.application.query.getworker.GetWorkerHandler;
import org.onechurch.childrenministry.sessions.application.query.getworker.GetWorkerQuery;
import org.onechurch.childrenministry.sessions.application.query.listsessions.ListSessionsHandler;
import org.onechurch.childrenministry.sessions.application.query.listsessions.ListSessionsQuery;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.childrenministry.sessions.presentation.api.SessionApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
@RestController
public class SessionController implements SessionApi {
    private final CreateLocationHandler createLocation;
    private final CreateRoomHandler createRoom;
    private final RegisterWorkerHandler registerWorker;
    private final ReviewWorkerHandler reviewWorker;
    private final CreateSessionHandler createSession;
    private final SetSessionStaffHandler setSessionStaff;
    private final SetSessionStatusHandler setSessionStatus;
    private final ListSessionsHandler listSessions;
    private final GetSessionHandler getSession;
    private final ListRoomsHandler listRooms;
    private final GetWorkerHandler getWorker;
    private final ListLocationsHandler listLocations;
    public SessionController(CreateLocationHandler createLocation, CreateRoomHandler createRoom,
        RegisterWorkerHandler registerWorker, ReviewWorkerHandler reviewWorker, CreateSessionHandler createSession,
        SetSessionStaffHandler setSessionStaff, SetSessionStatusHandler setSessionStatus,
        ListSessionsHandler listSessions, GetSessionHandler getSession, ListRoomsHandler listRooms,
        GetWorkerHandler getWorker, ListLocationsHandler listLocations) {
        this.createLocation = createLocation;
        this.createRoom = createRoom;
        this.registerWorker = registerWorker;
        this.reviewWorker = reviewWorker;
        this.createSession = createSession;
        this.setSessionStaff = setSessionStaff;
        this.setSessionStatus = setSessionStatus;
        this.listSessions = listSessions;
        this.getSession = getSession;
        this.listRooms = listRooms;
        this.getWorker = getWorker;
        this.listLocations = listLocations;
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public Created createLocation(CreateLocationRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(createLocation
            .handle(new CreateLocationCommand(UUID.fromString(principal.getName()), body.name(), body.timeZone())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public Created createRoom(CreateRoomRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(createRoom.handle(new CreateRoomCommand(UUID.fromString(principal.getName()),
            body.locationId(), body.name(), body.capacity())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public void registerWorker(RegisterWorkerRequest body, OAuth2AuthenticatedPrincipal principal) {
        registerWorker.handle(new RegisterWorkerCommand(UUID.fromString(principal.getName()), body.personId(),
            body.accountId(), body.screenedUntil(), body.trainedUntil(), body.evidenceReference()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public void reviewWorker(ReviewWorkerRequest body, OAuth2AuthenticatedPrincipal principal) {
        reviewWorker.handle(
            new ReviewWorkerCommand(UUID.fromString(principal.getName()), body.personId(), body.expectedVersion(),
                body.screenedUntil(), body.trainedUntil(), body.evidenceReference(), body.revoked()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public Created createSession(CreateSessionRequest body, OAuth2AuthenticatedPrincipal principal) {
        return new Created(createSession.handle(new CreateSessionCommand(UUID.fromString(principal.getName()),
            body.classId(), body.roomId(), body.startsAt(), body.endsAt(), body.seatLimit())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public void setSessionStaff(SetSessionStaffRequest body, OAuth2AuthenticatedPrincipal principal) {
        setSessionStaff.handle(new SetSessionStaffCommand(UUID.fromString(principal.getName()), body.sessionId(),
            body.expectedVersion(), body.workerId(), body.role(), body.present()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public void setSessionStatus(SetSessionStatusRequest body, OAuth2AuthenticatedPrincipal principal) {
        setSessionStatus.handle(new SetSessionStatusCommand(UUID.fromString(principal.getName()), body.sessionId(),
            body.expectedVersion(), body.status(), body.reason(), Boolean.TRUE.equals(body.acknowledgeIncidents())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public List<SessionView> listSessions(UUID locationId, Instant from, Instant until, int limit, int offset) {
        return listSessions.handle(new ListSessionsQuery(locationId, from, until, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public SessionView getSession(UUID sessionId) {
        return getSession.handle(new GetSessionQuery(sessionId));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public List<Room> listRooms(UUID locationId) {
        return listRooms.handle(new ListRoomsQuery(locationId));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public Worker getWorker(UUID personId) {
        return getWorker.handle(new GetWorkerQuery(personId));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public List<Location> listLocations(int limit, int offset) {
        return listLocations.handle(new ListLocationsQuery(limit, offset));
    }
}
