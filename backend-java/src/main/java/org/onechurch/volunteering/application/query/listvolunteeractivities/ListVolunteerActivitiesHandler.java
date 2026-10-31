package org.onechurch.volunteering.application.query.listvolunteeractivities;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.ActivityRegistry;
import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.application.contract.ActivitySource;

import java.util.List;

public final class ListVolunteerActivitiesHandler {
    private final PermissionChecker auth;
    private final ActivityRegistry activities;

    public ListVolunteerActivitiesHandler(PermissionChecker auth, ActivityRegistry activities) {
        this.auth = auth;
        this.activities = activities;
    }
    public List<ActivitySource.Activity> handle(ListVolunteerActivitiesQuery q) {
        auth.requirePermission("volunteering.read");
        ServingViews.range(q.from(), q.until());
        ServingViews.page(q.limit(), q.offset());
        return activities.upcoming(q.kind(), q.from(), q.until(), q.limit(), q.offset());
    }
}
