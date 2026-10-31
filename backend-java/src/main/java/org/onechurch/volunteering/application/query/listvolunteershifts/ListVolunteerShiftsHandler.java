package org.onechurch.volunteering.application.query.listvolunteershifts;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ServingViews;

public final class ListVolunteerShiftsHandler {
    private final PermissionChecker auth;
    private final ServingViews views;

    public ListVolunteerShiftsHandler(PermissionChecker auth, ServingViews views) {
        this.auth = auth;
        this.views = views;
    }
    public ServingViews.Shifts handle(ListVolunteerShiftsQuery q) {
        auth.requirePermission("volunteering.read");
        return views.shifts(q.from(), q.until(), q.teamId(), q.limit(), q.offset());
    }
}
