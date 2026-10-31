package org.onechurch.volunteering.application.query.getvolunteerreadiness;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.VolunteerReadiness;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.domain.VolunteerException;

public final class GetVolunteerReadinessHandler {
    private final PermissionChecker auth;
    private final VolunteerReadiness readiness;
    private final RosterReader roster;

    public GetVolunteerReadinessHandler(PermissionChecker auth, VolunteerReadiness readiness, RosterReader roster) {
        this.auth = auth;
        this.readiness = readiness;
        this.roster = roster;
    }
    public VolunteerReadiness.Assessment handle(GetVolunteerReadinessQuery q) {
        auth.requirePermission("volunteering.read");
        return readiness.assess(q.volunteerId(),
            roster.shift(q.shiftId()).orElseThrow(() -> VolunteerException.missing("Shift")), null);
    }
}
