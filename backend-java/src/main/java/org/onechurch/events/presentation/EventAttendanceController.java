package org.onechurch.events.presentation;
import org.onechurch.events.application.EventAttendanceOperations;
import org.onechurch.events.application.command.checkineventperson.CheckInEventPersonCommand;
import org.onechurch.events.application.command.checkineventperson.CheckInEventPersonHandler;
import org.onechurch.events.application.command.correcteventattendance.CorrectEventAttendanceCommand;
import org.onechurch.events.application.command.correcteventattendance.CorrectEventAttendanceHandler;
import org.onechurch.events.application.command.registereventwalkin.RegisterEventWalkInCommand;
import org.onechurch.events.application.command.registereventwalkin.RegisterEventWalkInHandler;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.application.query.geteventattendancehistory.GetEventAttendanceHistoryHandler;
import org.onechurch.events.application.query.geteventattendancehistory.GetEventAttendanceHistoryQuery;
import org.onechurch.events.application.query.listeventattendance.ListEventAttendanceHandler;
import org.onechurch.events.application.query.listeventattendance.ListEventAttendanceQuery;
import org.onechurch.events.presentation.api.EventAttendanceApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
public class EventAttendanceController implements EventAttendanceApi {
    private final CheckInEventPersonHandler checkInEventPerson;
    private final RegisterEventWalkInHandler registerEventWalkIn;
    private final CorrectEventAttendanceHandler correctEventAttendance;
    private final ListEventAttendanceHandler listEventAttendance;
    private final GetEventAttendanceHistoryHandler getEventAttendanceHistory;
    public EventAttendanceController(CheckInEventPersonHandler checkInEventPerson,
        RegisterEventWalkInHandler registerEventWalkIn, CorrectEventAttendanceHandler correctEventAttendance,
        ListEventAttendanceHandler listEventAttendance, GetEventAttendanceHistoryHandler getEventAttendanceHistory) {
        this.checkInEventPerson = checkInEventPerson;
        this.registerEventWalkIn = registerEventWalkIn;
        this.correctEventAttendance = correctEventAttendance;
        this.listEventAttendance = listEventAttendance;
        this.getEventAttendanceHistory = getEventAttendanceHistory;
    }
    @Override
    @PreAuthorize("hasAuthority('events.attendance.manage')")
    public Created checkInEventPerson(UUID id, CheckInEventPersonBody b, OAuth2AuthenticatedPrincipal p) {
        return new Created(checkInEventPerson.handle(
            new CheckInEventPersonCommand(UUID.fromString(p.getName()), b.requestId(), id, b.expectedVersion())));
    }
    @Override
    @PreAuthorize("hasAuthority('events.attendance.manage')")
    public EventAttendanceOperations.WalkIn registerEventWalkIn(UUID id, RegisterEventWalkInBody b,
        OAuth2AuthenticatedPrincipal p) {
        return registerEventWalkIn.handle(new RegisterEventWalkInCommand(UUID.fromString(p.getName()), b.requestId(),
            id, b.personId(), b.expectedVersion()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.attendance.manage')")
    public void correctEventAttendance(UUID id, CorrectEventAttendanceBody b, OAuth2AuthenticatedPrincipal p) {
        correctEventAttendance.handle(new CorrectEventAttendanceCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.status(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('events.attendance.read')")
    public AttendanceReader.Page listEventAttendance(UUID id, String search, String status, int limit, int offset) {
        return listEventAttendance.handle(new ListEventAttendanceQuery(id, search, status, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('events.attendance.read')")
    public List<AttendanceReader.History> getEventAttendanceHistory(UUID id, int limit, int offset) {
        return getEventAttendanceHistory.handle(new GetEventAttendanceHistoryQuery(id, limit, offset));
    }
}
