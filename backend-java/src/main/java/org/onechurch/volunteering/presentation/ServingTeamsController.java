package org.onechurch.volunteering.presentation;

import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.application.command.approveservingrole.ApproveServingRoleCommand;
import org.onechurch.volunteering.application.command.approveservingrole.ApproveServingRoleHandler;
import org.onechurch.volunteering.application.command.createservingrole.CreateServingRoleCommand;
import org.onechurch.volunteering.application.command.createservingrole.CreateServingRoleHandler;
import org.onechurch.volunteering.application.command.createservingteam.CreateServingTeamCommand;
import org.onechurch.volunteering.application.command.createservingteam.CreateServingTeamHandler;
import org.onechurch.volunteering.application.command.joinservingteam.JoinServingTeamCommand;
import org.onechurch.volunteering.application.command.joinservingteam.JoinServingTeamHandler;
import org.onechurch.volunteering.application.command.revokeservingapproval.RevokeServingApprovalCommand;
import org.onechurch.volunteering.application.command.revokeservingapproval.RevokeServingApprovalHandler;
import org.onechurch.volunteering.application.command.updateservingteam.UpdateServingTeamCommand;
import org.onechurch.volunteering.application.command.updateservingteam.UpdateServingTeamHandler;
import org.onechurch.volunteering.application.query.getservingteam.GetServingTeamHandler;
import org.onechurch.volunteering.application.query.getservingteam.GetServingTeamQuery;
import org.onechurch.volunteering.application.query.listservingteams.ListServingTeamsHandler;
import org.onechurch.volunteering.application.query.listservingteams.ListServingTeamsQuery;
import org.onechurch.volunteering.presentation.api.ServingTeamsApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;

@RestController
public class ServingTeamsController implements ServingTeamsApi {
    private final CreateServingTeamHandler createServingTeam;
    private final UpdateServingTeamHandler updateServingTeam;
    private final CreateServingRoleHandler createServingRole;
    private final JoinServingTeamHandler joinServingTeam;
    private final ApproveServingRoleHandler approveServingRole;
    private final RevokeServingApprovalHandler revokeServingApproval;
    private final ListServingTeamsHandler listServingTeams;
    private final GetServingTeamHandler getServingTeam;
    public ServingTeamsController(CreateServingTeamHandler createServingTeam,
        UpdateServingTeamHandler updateServingTeam, CreateServingRoleHandler createServingRole,
        JoinServingTeamHandler joinServingTeam, ApproveServingRoleHandler approveServingRole,
        RevokeServingApprovalHandler revokeServingApproval, ListServingTeamsHandler listServingTeams,
        GetServingTeamHandler getServingTeam) {
        this.createServingTeam = createServingTeam;
        this.updateServingTeam = updateServingTeam;
        this.createServingRole = createServingRole;
        this.joinServingTeam = joinServingTeam;
        this.approveServingRole = approveServingRole;
        this.revokeServingApproval = revokeServingApproval;
        this.listServingTeams = listServingTeams;
        this.getServingTeam = getServingTeam;
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public Created createTeam(TeamDraft b, OAuth2AuthenticatedPrincipal p) {
        return new Created(
            createServingTeam.handle(new CreateServingTeamCommand(UUID.fromString(p.getName()), b.name())));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public void updateTeam(UUID id, TeamEdit b, OAuth2AuthenticatedPrincipal p) {
        updateServingTeam.handle(
            new UpdateServingTeamCommand(UUID.fromString(p.getName()), id, b.expectedVersion(), b.name(), b.active()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public Created role(UUID id, Role b, OAuth2AuthenticatedPrincipal p) {
        return new Created(createServingRole.handle(new CreateServingRoleCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.name(), b.childrenRole(), b.requiredCredentials())));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public Created join(UUID id, Membership b, OAuth2AuthenticatedPrincipal p) {
        return new Created(joinServingTeam.handle(new JoinServingTeamCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.volunteerId(), b.validFrom(), b.validUntil())));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public Created approve(UUID id, Approval b, OAuth2AuthenticatedPrincipal p) {
        return new Created(approveServingRole.handle(new ApproveServingRoleCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.volunteerId(), b.roleId(), b.validFrom(), b.validUntil())));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.manage')")
    public void revoke(UUID id, Revoke b, OAuth2AuthenticatedPrincipal p) {
        revokeServingApproval.handle(new RevokeServingApprovalCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.volunteerId(), b.recordId(), b.roleApproval()));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public ServingViews.Teams teams(int limit, int offset) {
        return listServingTeams.handle(new ListServingTeamsQuery(limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('volunteering.read')")
    public ServingViews.TeamDetails team(UUID id) {
        return getServingTeam.handle(new GetServingTeamQuery(id));
    }
}
