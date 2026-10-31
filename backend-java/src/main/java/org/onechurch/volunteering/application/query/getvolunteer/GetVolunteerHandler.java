package org.onechurch.volunteering.application.query.getvolunteer;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ServingViews;

public final class GetVolunteerHandler {
    private final PermissionChecker auth;
    private final ServingViews views;

    public GetVolunteerHandler(PermissionChecker auth, ServingViews views) {
        this.auth = auth;
        this.views = views;
    }
    public ServingViews.Profile handle(GetVolunteerQuery q) {
        auth.requirePermission("volunteering.read");
        return views.profile(q.id());
    }
}
