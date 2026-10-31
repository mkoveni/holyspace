package org.onechurch.volunteering.application.query.listservingteams;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ServingViews;

public final class ListServingTeamsHandler {
    private final PermissionChecker auth;
    private final ServingViews views;

    public ListServingTeamsHandler(PermissionChecker auth, ServingViews views) {
        this.auth = auth;
        this.views = views;
    }
    public ServingViews.Teams handle(ListServingTeamsQuery q) {
        auth.requirePermission("volunteering.read");
        return views.teams(q.limit(), q.offset());
    }
}
