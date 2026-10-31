package org.onechurch.childrenministry.sessions.application.query.listplanningworkers;
import org.onechurch.childrenministry.sessions.application.TeamPlanning;
import org.onechurch.childrenministry.sessions.application.TeamPlanningView;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
public final class ListPlanningWorkersHandler {
    private final PermissionChecker authorization;
    private final TeamPlanning planning;
    public ListPlanningWorkersHandler(PermissionChecker authorization, TeamPlanning planning) {
        this.authorization = authorization;
        this.planning = planning;
    }
    public TeamPlanningView.Workers handle(ListPlanningWorkersQuery query) {
        authorization.requirePermission("children.manage");
        return planning.workers(query.search(), query.limit(), query.offset());
    }
}
