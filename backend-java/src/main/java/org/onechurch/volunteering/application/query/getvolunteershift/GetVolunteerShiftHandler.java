package org.onechurch.volunteering.application.query.getvolunteershift;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ServingViews;

public final class GetVolunteerShiftHandler {
    private final PermissionChecker auth;
    private final ServingViews views;

    public GetVolunteerShiftHandler(PermissionChecker auth, ServingViews views) {
        this.auth = auth;
        this.views = views;
    }
    public ServingViews.ShiftView handle(GetVolunteerShiftQuery q) {
        auth.requirePermission("volunteering.read");
        return views.shift(q.id());
    }
}
