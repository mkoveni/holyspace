package org.onechurch.volunteering.application;
import org.onechurch.volunteering.application.contract.ActivitySource;
import org.onechurch.volunteering.application.contract.MemberDutyReader;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.Volunteer;

import java.time.Clock;
import java.time.Instant;
import java.time.temporal.ChronoUnit;
import java.util.List;
import java.util.UUID;
public final class MemberVolunteerViews {
    public record Invitation(UUID id, long version, String status, Instant expiresAt) {}
    public record Duty(UUID id, String status, String shiftStatus, String team, String role, String activity,
        String location, Instant startsAt, Instant endsAt, VolunteerReadiness.Assessment readiness,
        Invitation invitation) {}
    public record Overview(boolean permitted, boolean linked, UUID volunteerId, String status, Long version,
        List<VolunteerProfiles.Weekly> weekly, List<VolunteerProfiles.ExceptionWindow> exceptions, List<Duty> items,
        boolean hasMore) {}
    private final VolunteerReader volunteers;
    private final MemberDutyReader duties;
    private final RosterReader roster;
    private final TeamReader teams;
    private final ServingViews views;
    private final VolunteerReadiness readiness;
    private final List<ActivitySource> sources;
    private final Clock clock;
    public MemberVolunteerViews(VolunteerReader volunteers, MemberDutyReader duties, RosterReader roster,
        TeamReader teams, ServingViews views, VolunteerReadiness readiness, List<ActivitySource> sources, Clock clock) {
        this.volunteers = volunteers;
        this.duties = duties;
        this.roster = roster;
        this.teams = teams;
        this.views = views;
        this.readiness = readiness;
        this.sources = List.copyOf(sources);
        this.clock = clock;
    }
    public Overview overview(UUID account, boolean permitted, int limit, int offset) {
        ServingViews.page(limit, offset);
        if (!permitted) {
            return empty(false);
        }
        return volunteers.byAccount(account).map(v -> overview(v, limit, offset)).orElseGet(() -> empty(true));
    }
    private Overview empty(boolean permitted) {
        return new Overview(permitted, false, null, null, null, List.of(), List.of(), List.of(), false);
    }
    private Overview overview(Volunteer v, int limit, int offset) {
        Instant now = clock.instant();
        List<Assignment> page = duties.duties(v.id(), now, now.plus(90, ChronoUnit.DAYS), limit + 1, offset);
        return new Overview(true, true, v.id(), v.status(), v.version(),
            volunteers.availability(v.id(), v.availabilityRevision())
                .stream()
                .map(r -> new VolunteerProfiles.Weekly(r.dayOfWeek(), r.startsAt(), r.endsAt(), r.timeZone(),
                    r.validFrom(), r.validUntil()))
                .toList(),
            volunteers.exceptions(v.id(), v.availabilityRevision())
                .stream()
                .map(e -> new VolunteerProfiles.ExceptionWindow(e.startsAt(), e.endsAt(), e.available()))
                .toList(),
            page.stream().limit(limit).map(this::duty).toList(), page.size() > limit);
    }
    private Duty duty(Assignment a) {
        Shift s = roster.shift(a.shiftId()).orElseThrow();
        ActivitySource.Activity activity = sources.stream()
            .filter(source -> source.kind().equals(s.sourceKind()))
            .findFirst()
            .flatMap(source -> source.find(s.sourceId()))
            .orElse(null);
        Invitation invitation = roster.invitations(a.id())
            .stream()
            .findFirst()
            .map(views::invitation)
            .map(i -> new Invitation(i.invitation().id(), i.invitation().version(), i.effectiveStatus(),
                i.invitation().expiresAt()))
            .orElse(null);
        return new Duty(a.id(), a.status(), s.status(), teams.team(s.teamId()).orElseThrow().name(),
            teams.role(s.roleId()).orElseThrow().name(), activity == null ? null : activity.label(),
            activity == null ? null : activity.venueLabel(), s.startsAt(), s.endsAt(),
            readiness.assess(a.volunteerId(), s, a.id()), invitation);
    }
}
