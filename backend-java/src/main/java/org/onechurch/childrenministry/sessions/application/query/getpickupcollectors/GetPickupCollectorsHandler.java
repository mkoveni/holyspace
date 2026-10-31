package org.onechurch.childrenministry.sessions.application.query.getpickupcollectors;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.application.StaffScope;
import org.onechurch.childrenministry.sessions.application.contract.AttendanceReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.Attendance;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.List;
import java.util.stream.Stream;
public final class GetPickupCollectorsHandler {
    private final PermissionChecker authorization;
    private final StaffScope scope;
    private final AttendanceReader attendance;
    private final SessionReader sessions;
    private final ParticipationReader participation;
    private final SafeguardingReader safeguarding;
    private final ParticipantAccess people;
    private final Clock clock;
    private final ZoneId zone;
    public GetPickupCollectorsHandler(PermissionChecker authorization, StaffScope scope, AttendanceReader attendance,
        SessionReader sessions, ParticipationReader participation, SafeguardingReader safeguarding,
        ParticipantAccess people, Clock clock, ZoneId zone) {
        this.authorization = authorization;
        this.scope = scope;
        this.attendance = attendance;
        this.sessions = sessions;
        this.participation = participation;
        this.safeguarding = safeguarding;
        this.people = people;
        this.clock = clock;
        this.zone = zone;
    }
    public List<PickupCollector> handle(GetPickupCollectorsQuery query) {
        authorization.requirePermission(query.actorId(), "children.station");
        Attendance value =
            attendance.attendance(query.attendanceId()).orElseThrow(() -> ChildrenException.missing("Attendance"));
        scope.require(query.actorId(), value.sessionId());
        MinistrySession session =
            sessions.ministrySession(value.sessionId()).orElseThrow(() -> ChildrenException.missing("Session"));
        MinistryClass group =
            participation.ministryClass(session.classId()).orElseThrow(() -> ChildrenException.missing("Class"));
        Instant now = clock.instant();
        LocalDate today = LocalDate.now(clock.withZone(zone));
        return safeguarding.authorizations(value.childId())
            .stream()
            .filter(grant -> grant.programmeId().equals(group.programmeId()) && grant.covers(now, now))
            .filter(grant -> safeguarding.guardianAuthority(grant.authorityId())
                .filter(authority -> authority.covers(now, now))
                .isPresent())
            .flatMap(grant -> {
                PersonDirectory.Entry collector = people.person(grant.collectorId());
                if (!collector.available() || collector.birthDate() == null
                    || collector.birthDate().plusYears(18).isAfter(today)) {
                    return Stream.<PickupCollector>empty();
                }
                return Stream.of(new PickupCollector(grant.id(), collector.id(), collector.name(), grant.validUntil()));
            })
            .toList();
    }
}
