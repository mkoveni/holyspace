package org.onechurch.volunteering.application.query.previewvolunteerschedule;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.RosterPlanning;
import org.onechurch.volunteering.application.ScheduleDates;

import java.util.List;

public final class PreviewVolunteerScheduleHandler {
    private final PermissionChecker auth;
    public PreviewVolunteerScheduleHandler(PermissionChecker auth) {
        this.auth = auth;
    }
    public List<RosterPlanning.Draft> handle(PreviewVolunteerScheduleQuery q) {
        auth.requirePermission("volunteering.read");
        return ScheduleDates.preview(q.requestId(), q.teamId(), q.roleId(), q.dates(), q.startsAt(), q.endsAt(),
            q.timeZone(), q.positions());
    }
}
