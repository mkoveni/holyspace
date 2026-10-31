package org.onechurch.childrenministry.sessions.application.query.getteamplanning;
import org.onechurch.childrenministry.sessions.application.TeamPlanning;
import org.onechurch.childrenministry.sessions.application.TeamPlanningView;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class GetTeamPlanningHandler {
    private final PermissionChecker authorization;
    private final TeamPlanning planning;
    public GetTeamPlanningHandler(PermissionChecker authorization, TeamPlanning planning) {
        this.authorization = authorization;
        this.planning = planning;
    }
    public TeamPlanningView handle(GetTeamPlanningQuery query) {
        authorization.requirePermission("children.manage");
        return planning.list(query.from(), query.until(), query.programmeId(), query.locationId(), query.search(),
            query.state(), query.limit(), query.offset());
    }
}
