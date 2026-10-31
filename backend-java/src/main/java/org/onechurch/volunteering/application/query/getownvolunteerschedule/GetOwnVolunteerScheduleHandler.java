package org.onechurch.volunteering.application.query.getownvolunteerschedule;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ServingViews;

import java.util.List;

public final class GetOwnVolunteerScheduleHandler {
    private final PermissionChecker auth;
    private final ServingViews views;

    public GetOwnVolunteerScheduleHandler(PermissionChecker auth, ServingViews views) {
        this.auth = auth;
        this.views = views;
    }
    public List<ServingViews.InvitationView> handle(GetOwnVolunteerScheduleQuery q) {
        auth.requirePermission("volunteering.serve");
        auth.requirePermission(q.actorId(), "volunteering.serve");
        return views.own(q.actorId(), q.limit(), q.offset());
    }
}
