package org.onechurch.volunteering.presentation;

import org.onechurch.volunteering.application.RosterPlanning;
import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.application.VolunteerReadiness;
import org.onechurch.volunteering.application.command.assignvolunteer.AssignVolunteerCommand;
import org.onechurch.volunteering.application.command.assignvolunteer.AssignVolunteerHandler;
import org.onechurch.volunteering.application.command.bulkcreatevolunteershifts.BulkCreateVolunteerShiftsCommand;
import org.onechurch.volunteering.application.command.bulkcreatevolunteershifts.BulkCreateVolunteerShiftsHandler;
import org.onechurch.volunteering.application.command.changevolunteerassignment.ChangeVolunteerAssignmentCommand;
import org.onechurch.volunteering.application.command.changevolunteerassignment.ChangeVolunteerAssignmentHandler;
import org.onechurch.volunteering.application.command.createvolunteershift.CreateVolunteerShiftCommand;
import org.onechurch.volunteering.application.command.createvolunteershift.CreateVolunteerShiftHandler;
import org.onechurch.volunteering.application.command.updatevolunteershift.UpdateVolunteerShiftCommand;
import org.onechurch.volunteering.application.command.updatevolunteershift.UpdateVolunteerShiftHandler;
import org.onechurch.volunteering.application.contract.ActivitySource;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.query.getassignmenthistory.GetAssignmentHistoryHandler;
import org.onechurch.volunteering.application.query.getassignmenthistory.GetAssignmentHistoryQuery;
import org.onechurch.volunteering.application.query.getvolunteerreadiness.GetVolunteerReadinessHandler;
import org.onechurch.volunteering.application.query.getvolunteerreadiness.GetVolunteerReadinessQuery;
import org.onechurch.volunteering.application.query.getvolunteershift.GetVolunteerShiftHandler;
import org.onechurch.volunteering.application.query.getvolunteershift.GetVolunteerShiftQuery;
import org.onechurch.volunteering.application.query.listvolunteeractivities.ListVolunteerActivitiesHandler;
import org.onechurch.volunteering.application.query.listvolunteeractivities.ListVolunteerActivitiesQuery;
import org.onechurch.volunteering.application.query.listvolunteershifts.ListVolunteerShiftsHandler;
import org.onechurch.volunteering.application.query.listvolunteershifts.ListVolunteerShiftsQuery;
import org.onechurch.volunteering.presentation.api.VolunteerRosterApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.time.Instant;
import java.util.List;
import java.util.UUID;

@RestController
public class VolunteerRosterController implements VolunteerRosterApi {
    private final CreateVolunteerShiftHandler createVolunteerShift;
    private final BulkCreateVolunteerShiftsHandler bulkCreateVolunteerShifts;
    private final UpdateVolunteerShiftHandler updateVolunteerShift;
    private final AssignVolunteerHandler assignVolunteer;
    private final ChangeVolunteerAssignmentHandler changeVolunteerAssignment;
    private final ListVolunteerShiftsHandler listVolunteerShifts;
    private final GetVolunteerShiftHandler getVolunteerShift;
    private final GetVolunteerReadinessHandler getVolunteerReadiness;
    private final ListVolunteerActivitiesHandler listVolunteerActivities;
    private final GetAssignmentHistoryHandler getAssignmentHistory;
    public VolunteerRosterController(CreateVolunteerShiftHandler createVolunteerShift,
        BulkCreateVolunteerShiftsHandler bulkCreateVolunteerShifts, UpdateVolunteerShiftHandler updateVolunteerShift,
        AssignVolunteerHandler assignVolunteer, ChangeVolunteerAssignmentHandler changeVolunteerAssignment,
        ListVolunteerShiftsHandler listVolunteerShifts, GetVolunteerShiftHandler getVolunteerShift,
        GetVolunteerReadinessHandler getVolunteerReadiness, ListVolunteerActivitiesHandler listVolunteerActivities,
        GetAssignmentHistoryHandler getAssignmentHistory) {
        this.createVolunteerShift = createVolunteerShift;
        this.bulkCreateVolunteerShifts = bulkCreateVolunteerShifts;
        this.updateVolunteerShift = updateVolunteerShift;
        this.assignVolunteer = assignVolunteer;
        this.changeVolunteerAssignment = changeVolunteerAssignment;
        this.listVolunteerShifts = listVolunteerShifts;
        this.getVolunteerShift = getVolunteerShift;
        this.getVolunteerReadiness = getVolunteerReadiness;
        this.listVolunteerActivities = listVolunteerActivities;
        this.getAssignmentHistory = getAssignmentHistory;
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public Created createShift(RosterPlanning.Draft b, OAuth2AuthenticatedPrincipal p) {
        return new Created(
            createVolunteerShift.handle(new CreateVolunteerShiftCommand(UUID.fromString(p.getName()), b)));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public List<UUID> bulkShifts(List<RosterPlanning.Draft> b, OAuth2AuthenticatedPrincipal p) {
        return bulkCreateVolunteerShifts.handle(new BulkCreateVolunteerShiftsCommand(UUID.fromString(p.getName()), b));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public void updateShift(UUID id, ShiftEdit b, OAuth2AuthenticatedPrincipal p) {
        updateVolunteerShift.handle(new UpdateVolunteerShiftCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.startsAt(), b.endsAt(), b.positions(), b.status()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public Created assign(Assign b, OAuth2AuthenticatedPrincipal p) {
        return new Created(assignVolunteer.handle(new AssignVolunteerCommand(UUID.fromString(p.getName()),
            b.requestId(), b.shiftId(), b.expectedVersion(), b.volunteerId(), b.status())));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public void changeAssignment(UUID id, AssignmentEdit b, OAuth2AuthenticatedPrincipal p) {
        changeVolunteerAssignment.handle(new ChangeVolunteerAssignmentCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.status(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public ServingViews.Shifts shifts(Instant from, Instant until, UUID teamId, int limit, int offset) {
        return listVolunteerShifts.handle(new ListVolunteerShiftsQuery(from, until, teamId, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public ServingViews.ShiftView shift(UUID id) {
        return getVolunteerShift.handle(new GetVolunteerShiftQuery(id));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public VolunteerReadiness.Assessment readiness(UUID volunteerId, UUID shiftId) {
        return getVolunteerReadiness.handle(new GetVolunteerReadinessQuery(volunteerId, shiftId));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public List<ActivitySource.Activity> activities(String kind, Instant from, Instant until, int limit, int offset) {
        return listVolunteerActivities.handle(new ListVolunteerActivitiesQuery(kind, from, until, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public List<RosterReader.Event> history(UUID id, int limit, int offset) {
        return getAssignmentHistory.handle(new GetAssignmentHistoryQuery(id, limit, offset));
    }
}
