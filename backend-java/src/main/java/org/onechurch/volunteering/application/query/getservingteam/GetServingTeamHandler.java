package org.onechurch.volunteering.application.query.getservingteam;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ServingViews;

public final class GetServingTeamHandler {
    private final PermissionChecker auth;
    private final ServingViews views;

    public GetServingTeamHandler(PermissionChecker auth, ServingViews views) {
        this.auth = auth;
        this.views = views;
    }
    public ServingViews.TeamDetails handle(GetServingTeamQuery q) {
        auth.requirePermission("volunteering.read");
        return views.team(q.id());
    }
}
