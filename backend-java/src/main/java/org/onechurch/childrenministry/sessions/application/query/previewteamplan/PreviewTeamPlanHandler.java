package org.onechurch.childrenministry.sessions.application.query.previewteamplan;
import org.onechurch.childrenministry.sessions.application.TeamPlanning;
import org.onechurch.childrenministry.sessions.application.TeamPlanningView;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class PreviewTeamPlanHandler {
    private final PermissionChecker authorization;
    private final TeamPlanning planning;
    public PreviewTeamPlanHandler(PermissionChecker authorization, TeamPlanning planning) {
        this.authorization = authorization;
        this.planning = planning;
    }
    public TeamPlanningView.Preview handle(PreviewTeamPlanQuery query) {
        authorization.requirePermission("children.manage");
        return planning.preview(query.draft());
    }
}
