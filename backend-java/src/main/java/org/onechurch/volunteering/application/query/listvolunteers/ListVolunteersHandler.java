package org.onechurch.volunteering.application.query.listvolunteers;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ServingViews;

public final class ListVolunteersHandler {
    private final PermissionChecker auth;
    private final ServingViews views;

    public ListVolunteersHandler(PermissionChecker auth, ServingViews views) {
        this.auth = auth;
        this.views = views;
    }
    public ServingViews.Volunteers handle(ListVolunteersQuery q) {
        auth.requirePermission("volunteering.read");
        return views.volunteers(q.search(), q.limit(), q.offset());
    }
}
