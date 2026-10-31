package org.onechurch.volunteering.infrastructure;

import org.onechurch.communications.application.contract.MemberNotifications;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.ActivityRegistry;
import org.onechurch.volunteering.application.ActivityStaffingSummary;
import org.onechurch.volunteering.application.PublishedStaffingDirectory;
import org.onechurch.volunteering.application.RosterPlanning;
import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.application.TeamManagement;
import org.onechurch.volunteering.application.VolunteerProfiles;
import org.onechurch.volunteering.application.VolunteerReadiness;
import org.onechurch.volunteering.application.command.approveservingrole.ApproveServingRoleHandler;
import org.onechurch.volunteering.application.command.assignvolunteer.AssignVolunteerHandler;
import org.onechurch.volunteering.application.command.bulkcreatevolunteershifts.BulkCreateVolunteerShiftsHandler;
import org.onechurch.volunteering.application.command.changevolunteerassignment.ChangeVolunteerAssignmentHandler;
import org.onechurch.volunteering.application.command.changevolunteerstatus.ChangeVolunteerStatusHandler;
import org.onechurch.volunteering.application.command.createcredentialtype.CreateCredentialTypeHandler;
import org.onechurch.volunteering.application.command.createservingrole.CreateServingRoleHandler;
import org.onechurch.volunteering.application.command.createservingteam.CreateServingTeamHandler;
import org.onechurch.volunteering.application.command.createvolunteershift.CreateVolunteerShiftHandler;
import org.onechurch.volunteering.application.command.expirevolunteerinvitations.ExpireVolunteerInvitationsHandler;
import org.onechurch.volunteering.application.command.invitevolunteer.InviteVolunteerHandler;
import org.onechurch.volunteering.application.command.joinservingteam.JoinServingTeamHandler;
import org.onechurch.volunteering.application.command.linkvolunteeraccount.LinkVolunteerAccountHandler;
import org.onechurch.volunteering.application.command.recordvolunteercredential.RecordVolunteerCredentialHandler;
import org.onechurch.volunteering.application.command.registervolunteer.RegisterVolunteerHandler;
import org.onechurch.volunteering.application.command.respondvolunteerinvitation.RespondVolunteerInvitationHandler;
import org.onechurch.volunteering.application.command.revokeservingapproval.RevokeServingApprovalHandler;
import org.onechurch.volunteering.application.command.revokevolunteercredential.RevokeVolunteerCredentialHandler;
import org.onechurch.volunteering.application.command.setvolunteeravailability.SetVolunteerAvailabilityHandler;
import org.onechurch.volunteering.application.command.updateservingrole.UpdateServingRoleHandler;
import org.onechurch.volunteering.application.command.updateservingteam.UpdateServingTeamHandler;
import org.onechurch.volunteering.application.command.updatevolunteershift.UpdateVolunteerShiftHandler;
import org.onechurch.volunteering.application.contract.ActivitySource;
import org.onechurch.volunteering.application.contract.ActivityStaffing;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.ServingActivityLocks;
import org.onechurch.volunteering.application.contract.ServingAudit;
import org.onechurch.volunteering.application.contract.StaffingDirectory;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.application.query.getassignmenthistory.GetAssignmentHistoryHandler;
import org.onechurch.volunteering.application.query.getownvolunteerschedule.GetOwnVolunteerScheduleHandler;
import org.onechurch.volunteering.application.query.getservingteam.GetServingTeamHandler;
import org.onechurch.volunteering.application.query.getvolunteer.GetVolunteerHandler;
import org.onechurch.volunteering.application.query.getvolunteerreadiness.GetVolunteerReadinessHandler;
import org.onechurch.volunteering.application.query.getvolunteershift.GetVolunteerShiftHandler;
import org.onechurch.volunteering.application.query.listcredentialtypes.ListCredentialTypesHandler;
import org.onechurch.volunteering.application.query.listservingteams.ListServingTeamsHandler;
import org.onechurch.volunteering.application.query.listvolunteeractivities.ListVolunteerActivitiesHandler;
import org.onechurch.volunteering.application.query.listvolunteers.ListVolunteersHandler;
import org.onechurch.volunteering.application.query.listvolunteershifts.ListVolunteerShiftsHandler;
import org.onechurch.volunteering.application.query.previewvolunteerschedule.PreviewVolunteerScheduleHandler;
import org.onechurch.volunteering.domain.repository.RosterRepository;
import org.onechurch.volunteering.domain.repository.TeamRepository;
import org.onechurch.volunteering.domain.repository.VolunteerRepository;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.util.List;

@Configuration
public class VolunteeringConfiguration {
    @Bean
    ActivityStaffing activityStaffing(RosterReader roster, VolunteerReadiness readiness) {
        return new ActivityStaffingSummary(roster, readiness);
    }

    @Bean
    LinkVolunteerAccountHandler linkVolunteerAccount(PermissionChecker auth, VolunteerProfiles profiles) {
        return new LinkVolunteerAccountHandler(auth, profiles);
    }

    @Bean
    UpdateServingRoleHandler updateServingRole(PermissionChecker auth, TeamManagement teams) {
        return new UpdateServingRoleHandler(auth, teams);
    }

    @Bean
    StaffingDirectory volunteerStaffingDirectory(RosterReader roster, VolunteerReader volunteers, TeamReader teams,
        VolunteerReadiness readiness, PersonDirectory people) {
        return new PublishedStaffingDirectory(roster, volunteers, teams, readiness, people);
    }

    @Bean
    PreviewVolunteerScheduleHandler previewVolunteerScheduleHandler(PermissionChecker auth) {
        return new PreviewVolunteerScheduleHandler(auth);
    }

    @Bean
    ActivityRegistry servingActivities(List<ActivitySource> sources) {
        return new ActivityRegistry(sources);
    }
    @Bean
    VolunteerProfiles volunteerProfiles(VolunteerRepository repository, VolunteerReader reader, PersonDirectory people,
        AccountDirectory accounts, UnitOfWork tx, ServingAudit audit, Clock clock, RosterReader roster,
        List<ServingActivityLocks> locks) {
        return new VolunteerProfiles(repository, reader, people, accounts, tx, audit, clock, roster, locks);
    }
    @Bean
    TeamManagement servingTeams(TeamRepository repository, TeamReader teams, VolunteerReader volunteers,
        VolunteerProfiles profiles, UnitOfWork tx, ServingAudit audit, Clock clock) {
        return new TeamManagement(repository, teams, volunteers, profiles, tx, audit, clock);
    }
    @Bean
    RosterPlanning rosterPlanning(RosterRepository repository, RosterReader roster, TeamReader teams,
        TeamRepository teamRepository, VolunteerProfiles profiles, VolunteerReader volunteers,
        ActivityRegistry activities, UnitOfWork tx, ServingAudit audit, Clock clock,
        MemberNotifications notifications) {
        return new RosterPlanning(repository, roster, teams, teamRepository, profiles, volunteers, activities, tx,
            audit, clock, notifications);
    }
    @Bean
    VolunteerReadiness volunteerReadiness(VolunteerReader volunteers, TeamReader teams, RosterReader roster,
        PersonDirectory people, ActivityRegistry activities) {
        return new VolunteerReadiness(volunteers, teams, roster, people, activities);
    }
    @Bean
    ServingViews servingViews(VolunteerReader volunteers, TeamReader teams, RosterReader roster, PersonDirectory people,
        VolunteerReadiness readiness, Clock clock) {
        return new ServingViews(volunteers, teams, roster, people, readiness, clock);
    }
    @Bean
    RegisterVolunteerHandler registerVolunteerHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        return new RegisterVolunteerHandler(auth, profiles);
    }
    @Bean
    ChangeVolunteerStatusHandler changeVolunteerStatusHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        return new ChangeVolunteerStatusHandler(auth, profiles);
    }
    @Bean
    CreateCredentialTypeHandler createCredentialTypeHandler(PermissionChecker auth, VolunteerProfiles profiles) {
        return new CreateCredentialTypeHandler(auth, profiles);
    }
    @Bean
    RecordVolunteerCredentialHandler recordVolunteerCredentialHandler(PermissionChecker auth,
        VolunteerProfiles profiles) {
        return new RecordVolunteerCredentialHandler(auth, profiles);
    }
    @Bean
    RevokeVolunteerCredentialHandler revokeVolunteerCredentialHandler(PermissionChecker auth,
        VolunteerProfiles profiles) {
        return new RevokeVolunteerCredentialHandler(auth, profiles);
    }
    @Bean
    SetVolunteerAvailabilityHandler setVolunteerAvailabilityHandler(PermissionChecker auth,
        VolunteerProfiles profiles) {
        return new SetVolunteerAvailabilityHandler(auth, profiles);
    }
    @Bean
    CreateServingTeamHandler createServingTeamHandler(PermissionChecker auth, TeamManagement teams) {
        return new CreateServingTeamHandler(auth, teams);
    }
    @Bean
    UpdateServingTeamHandler updateServingTeamHandler(PermissionChecker auth, TeamManagement teams) {
        return new UpdateServingTeamHandler(auth, teams);
    }
    @Bean
    CreateServingRoleHandler createServingRoleHandler(PermissionChecker auth, TeamManagement teams) {
        return new CreateServingRoleHandler(auth, teams);
    }
    @Bean
    JoinServingTeamHandler joinServingTeamHandler(PermissionChecker auth, TeamManagement teams) {
        return new JoinServingTeamHandler(auth, teams);
    }
    @Bean
    ApproveServingRoleHandler approveServingRoleHandler(PermissionChecker auth, TeamManagement teams) {
        return new ApproveServingRoleHandler(auth, teams);
    }
    @Bean
    RevokeServingApprovalHandler revokeServingApprovalHandler(PermissionChecker auth, TeamManagement teams) {
        return new RevokeServingApprovalHandler(auth, teams);
    }
    @Bean
    CreateVolunteerShiftHandler createVolunteerShiftHandler(PermissionChecker auth, RosterPlanning roster) {
        return new CreateVolunteerShiftHandler(auth, roster);
    }
    @Bean
    BulkCreateVolunteerShiftsHandler bulkCreateVolunteerShiftsHandler(PermissionChecker auth, RosterPlanning roster) {
        return new BulkCreateVolunteerShiftsHandler(auth, roster);
    }
    @Bean
    UpdateVolunteerShiftHandler updateVolunteerShiftHandler(PermissionChecker auth, RosterPlanning roster) {
        return new UpdateVolunteerShiftHandler(auth, roster);
    }
    @Bean
    AssignVolunteerHandler assignVolunteerHandler(PermissionChecker auth, RosterPlanning roster) {
        return new AssignVolunteerHandler(auth, roster);
    }
    @Bean
    ChangeVolunteerAssignmentHandler changeVolunteerAssignmentHandler(PermissionChecker auth, RosterPlanning roster) {
        return new ChangeVolunteerAssignmentHandler(auth, roster);
    }
    @Bean
    InviteVolunteerHandler inviteVolunteerHandler(PermissionChecker auth, RosterPlanning roster) {
        return new InviteVolunteerHandler(auth, roster);
    }
    @Bean
    RespondVolunteerInvitationHandler respondVolunteerInvitationHandler(PermissionChecker auth, RosterPlanning roster) {
        return new RespondVolunteerInvitationHandler(auth, roster);
    }
    @Bean
    ExpireVolunteerInvitationsHandler expireVolunteerInvitationsHandler(PermissionChecker auth, RosterPlanning roster) {
        return new ExpireVolunteerInvitationsHandler(auth, roster);
    }
    @Bean
    ListVolunteersHandler listVolunteersHandler(PermissionChecker auth, ServingViews views) {
        return new ListVolunteersHandler(auth, views);
    }
    @Bean
    GetVolunteerHandler getVolunteerHandler(PermissionChecker auth, ServingViews views) {
        return new GetVolunteerHandler(auth, views);
    }
    @Bean
    ListServingTeamsHandler listServingTeamsHandler(PermissionChecker auth, ServingViews views) {
        return new ListServingTeamsHandler(auth, views);
    }
    @Bean
    GetServingTeamHandler getServingTeamHandler(PermissionChecker auth, ServingViews views) {
        return new GetServingTeamHandler(auth, views);
    }
    @Bean
    ListVolunteerShiftsHandler listVolunteerShiftsHandler(PermissionChecker auth, ServingViews views) {
        return new ListVolunteerShiftsHandler(auth, views);
    }
    @Bean
    GetVolunteerShiftHandler getVolunteerShiftHandler(PermissionChecker auth, ServingViews views) {
        return new GetVolunteerShiftHandler(auth, views);
    }
    @Bean
    GetVolunteerReadinessHandler getVolunteerReadinessHandler(PermissionChecker auth, VolunteerReadiness readiness,
        RosterReader roster) {
        return new GetVolunteerReadinessHandler(auth, readiness, roster);
    }
    @Bean
    ListCredentialTypesHandler listCredentialTypesHandler(PermissionChecker auth, VolunteerReader volunteers) {
        return new ListCredentialTypesHandler(auth, volunteers);
    }
    @Bean
    GetOwnVolunteerScheduleHandler getOwnVolunteerScheduleHandler(PermissionChecker auth, ServingViews views) {
        return new GetOwnVolunteerScheduleHandler(auth, views);
    }
    @Bean
    ListVolunteerActivitiesHandler listVolunteerActivitiesHandler(PermissionChecker auth, ActivityRegistry activities) {
        return new ListVolunteerActivitiesHandler(auth, activities);
    }
    @Bean
    GetAssignmentHistoryHandler getAssignmentHistoryHandler(PermissionChecker auth, RosterReader roster) {
        return new GetAssignmentHistoryHandler(auth, roster);
    }
}
