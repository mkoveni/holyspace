package org.onechurch.childrenministry.sessions.presentation;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingFollowUpView;
import org.onechurch.childrenministry.safeguarding.application.query.getsafeguardingfollowup.GetSafeguardingFollowUpHandler;
import org.onechurch.childrenministry.safeguarding.application.query.getsafeguardingfollowup.GetSafeguardingFollowUpQuery;
import org.onechurch.childrenministry.sessions.application.TeamPlanningView;
import org.onechurch.childrenministry.sessions.application.command.applyteamplan.ApplyTeamPlanCommand;
import org.onechurch.childrenministry.sessions.application.command.applyteamplan.ApplyTeamPlanHandler;
import org.onechurch.childrenministry.sessions.application.query.getteamplanning.GetTeamPlanningHandler;
import org.onechurch.childrenministry.sessions.application.query.getteamplanning.GetTeamPlanningQuery;
import org.onechurch.childrenministry.sessions.application.query.listplanningworkers.ListPlanningWorkersHandler;
import org.onechurch.childrenministry.sessions.application.query.listplanningworkers.ListPlanningWorkersQuery;
import org.onechurch.childrenministry.sessions.application.query.previewteamplan.PreviewTeamPlanHandler;
import org.onechurch.childrenministry.sessions.application.query.previewteamplan.PreviewTeamPlanQuery;
import org.onechurch.childrenministry.sessions.presentation.api.CoordinatorPlanningApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.time.LocalDate;
import java.util.UUID;
@RestController
public class CoordinatorPlanningController implements CoordinatorPlanningApi {
    private final GetTeamPlanningHandler team;
    private final ListPlanningWorkersHandler workers;
    private final PreviewTeamPlanHandler preview;
    private final ApplyTeamPlanHandler apply;
    private final GetSafeguardingFollowUpHandler followUp;
    public CoordinatorPlanningController(GetTeamPlanningHandler team, ListPlanningWorkersHandler workers,
        PreviewTeamPlanHandler preview, ApplyTeamPlanHandler apply, GetSafeguardingFollowUpHandler followUp) {
        this.team = team;
        this.workers = workers;
        this.preview = preview;
        this.apply = apply;
        this.followUp = followUp;
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public TeamPlanningView team(LocalDate from, LocalDate until, UUID programmeId, UUID locationId, String search,
        String state, int limit, int offset) {
        return team
            .handle(new GetTeamPlanningQuery(from, until, programmeId, locationId, search, state, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public TeamPlanningView.Workers workers(String search, int limit, int offset) {
        return workers.handle(new ListPlanningWorkersQuery(search, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public TeamPlanningView.Preview preview(Draft body) {
        return preview.handle(new PreviewTeamPlanQuery(body.toDraft()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.manage')")
    public void apply(Apply body, OAuth2AuthenticatedPrincipal principal) {
        apply.handle(
            new ApplyTeamPlanCommand(UUID.fromString(principal.getName()), body.draft().toDraft(), body.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public SafeguardingFollowUpView followUp(UUID programmeId, String search, String kind, String state, int days,
        int limit, int offset) {
        return followUp.handle(new GetSafeguardingFollowUpQuery(programmeId, search, kind, state, days, limit, offset));
    }
}
