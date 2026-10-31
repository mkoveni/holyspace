package org.onechurch.childrenministry.reporting.application.query.getministryoverview;
import org.onechurch.childrenministry.incidents.application.contract.IncidentReader;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.reporting.application.contract.MinistryOverviewReader;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingFollowUp;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingFollowUpView;
import org.onechurch.childrenministry.sessions.application.TeamPlanning;
import org.onechurch.childrenministry.sessions.application.TeamPlanningView;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
public final class GetMinistryOverviewHandler {
    public record View(Instant checkedAt, String timeZone, MinistryOverviewReader.Upcoming upcoming,
        TeamPlanningView teams, String teamNotice, SafeguardingFollowUpView safeguarding, String safeguardingNotice,
        IncidentReader.Page incidents, DeliveryReader.Page communications) {}
    private final PermissionChecker auth;
    private final MinistryOverviewReader overview;
    private final TeamPlanning teams;
    private final SafeguardingFollowUp safeguarding;
    private final IncidentReader incidents;
    private final DeliveryReader deliveries;
    private final Clock clock;
    private final ZoneId zone;
    public GetMinistryOverviewHandler(PermissionChecker auth, MinistryOverviewReader overview, TeamPlanning teams,
        SafeguardingFollowUp safeguarding, IncidentReader incidents, DeliveryReader deliveries, Clock clock,
        ZoneId zone) {
        this.auth = auth;
        this.overview = overview;
        this.teams = teams;
        this.safeguarding = safeguarding;
        this.incidents = incidents;
        this.deliveries = deliveries;
        this.clock = clock;
        this.zone = zone;
    }
    public View handle(GetMinistryOverviewQuery q) {
        String permission = auth.hasPermission("children.read") ? "children.read"
            : auth.hasPermission("children.safeguarding") ? "children.safeguarding" : "children.incidents.manage";
        auth.requirePermission(permission);
        Instant now = clock.instant();
        LocalDate today = now.atZone(zone).toLocalDate();
        MinistryOverviewReader.Upcoming upcoming = auth.hasPermission("children.read")
            ? overview.upcoming(now, today.plusDays(7).atStartOfDay(zone).toInstant()) : null;
        TeamPlanningView team = null;
        String teamNotice = "";
        if (auth.hasPermission("children.manage")) {
            try {
                team = teams.list(today, today.plusDays(6), null, null, "", "ATTENTION", 5, 0);
            } catch (ChildrenException bounded) {
                teamNotice = "Narrow the planning dates or programme in Team planning to assess this large schedule.";
            }
        }
        SafeguardingFollowUpView followup = null;
        String safeguardingNotice = "";
        if (auth.hasPermission("children.safeguarding")) {
            try {
                followup = safeguarding.list(null, "", "ALL", "ALL", 30, 5, 0, auth.hasPermission("children.support"));
            } catch (ChildrenException bounded) {
                safeguardingNotice = "Choose a programme in Safeguarding follow-up to review this large cohort.";
            }
        }
        return new View(now, zone.getId(), upcoming, team, teamNotice, followup, safeguardingNotice,
            auth.hasPermission("children.incidents.manage") ? incidents.list(null, null, "ACTIVE", false, today, 5, 0)
                : null,
            auth.hasPermission("children.safeguarding") ? deliveries.list("ALL", 5, 0) : null);
    }
}
