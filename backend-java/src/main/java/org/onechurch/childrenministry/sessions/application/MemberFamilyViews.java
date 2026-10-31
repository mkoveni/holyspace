package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.safeguarding.application.ChildReadiness;
import org.onechurch.childrenministry.safeguarding.application.ReadinessView;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.sessions.application.contract.MemberFamilyReader;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.List;
import java.util.Map;
import java.util.UUID;
public final class MemberFamilyViews {
    public record FamilyChild(UUID id, String name) {}
    public record FamilyPage<T>(boolean verified, List<T> items, boolean hasMore) {}
    public record FamilyProgramme(UUID id, String name, LocalDate validFrom, LocalDate validUntil, String className,
        List<ReadinessView.Step> readiness, String timeZone) {}
    public record FamilySession(UUID id, String programme, String className, String room, String location,
        String timeZone, Instant startsAt, Instant endsAt, String status, List<String> issues,
        boolean arrivalWindowOpen) {}
    public record FamilyVisit(MemberFamilyReader.FamilyAttendance visit, boolean canRequestPickupLabel) {}
    private final MemberFamilyReader reader;
    private final PersonDirectory people;
    private final ParticipationReader participation;
    private final ChildReadiness readiness;
    private final SessionChildPreparation preparation;
    private final SafeguardingReader safeguarding;
    private final Clock clock;
    private final ZoneId zone;
    public MemberFamilyViews(MemberFamilyReader reader, PersonDirectory people, ParticipationReader participation,
        ChildReadiness readiness, SessionChildPreparation preparation, SafeguardingReader safeguarding,
        SessionReader sessionReader, Clock clock, ZoneId zone) {
        this.sessionReader = sessionReader;
        this.reader = reader;
        this.people = people;
        this.participation = participation;
        this.readiness = readiness;
        this.preparation = preparation;
        this.safeguarding = safeguarding;
        this.clock = clock;
        this.zone = zone;
    }
    public void guardian(UUID person) {
        ChildrenRules.adult(people.get(person)
            .filter(PersonDirectory.Entry::available)
            .orElseThrow(() -> ChildrenException.missing("Guardian"))
            .birthDate(), LocalDate.now(clock.withZone(zone)));
    }
    public FamilyChild require(UUID guardian, UUID child) {
        guardian(guardian);
        if (!reader.authorized(guardian, child, clock.instant())) {
            throw ChildrenException.missing("Authorized child");
        }
        PersonDirectory.Entry person = people.get(child)
            .filter(PersonDirectory.Entry::available)
            .orElseThrow(() -> ChildrenException.missing("Authorized child"));
        try {
            ChildrenRules.child(person.birthDate(), LocalDate.now(clock.withZone(zone)));
        } catch (ChildrenException invalid) {
            throw ChildrenException.missing("Authorized child");
        }
        return new FamilyChild(person.id(), person.name());
    }
    public FamilyPage<FamilyChild> children(UUID guardian, int limit, int offset) {
        ChildrenRules.page(limit, offset);
        guardian(guardian);
        List<UUID> ids = reader.children(guardian, clock.instant(), limit + 1, offset);
        List<FamilyChild> children = people.getAll(ids.stream().limit(limit).toList())
            .stream()
            .filter(PersonDirectory.Entry::available)
            .filter(p -> p.birthDate() != null && !p.birthDate().isAfter(LocalDate.now(clock.withZone(zone)))
                && p.birthDate().plusYears(18).isAfter(LocalDate.now(clock.withZone(zone))))
            .map(p -> new FamilyChild(p.id(), p.name()))
            .toList();
        return new FamilyPage<>(true, children, ids.size() > limit);
    }
    public FamilyPage<FamilyProgramme> programmes(UUID guardian, UUID child, int limit, int offset) {
        require(guardian, child);
        ChildrenRules.page(limit, offset);
        LocalDate on = LocalDate.now(clock.withZone(zone));
        List<MemberFamilyReader.Programme> page = reader.programmes(child, on, limit + 1, offset);
        return new FamilyPage<>(true, page.stream().limit(limit).map(p -> {
            ReadinessView assessment =
                readiness.assess(participation.programme(p.id()).orElseThrow(), List.of(child)).get(child);
            String className = participation.classes(p.id())
                .stream()
                .filter(c -> participation.assignmentAt(c.id(), child, on).isPresent())
                .map(c -> c.name())
                .findFirst()
                .orElse(null);
            return new FamilyProgramme(p.id(), p.name(), p.validFrom(), p.validUntil(), className, assessment.steps(),
                assessment.timeZone());
        }).toList(), page.size() > limit);
    }
    public FamilyPage<FamilySession> sessions(UUID guardian, UUID child, int limit, int offset) {
        require(guardian, child);
        ChildrenRules.page(limit, offset);
        Instant now = clock.instant();
        List<MemberFamilyReader.Session> page =
            reader.sessions(child, now, now.plusSeconds(90L * 86400), limit + 1, offset);
        PersonDirectory.Entry person = people.get(child).orElseThrow();
        return new FamilyPage<>(true, page.stream().limit(limit).map(r -> {
            MinistrySession s = r.session();
            boolean open;
            try {
                s.requireArrivalWindow(now);
                open = true;
            } catch (ChildrenException closed) {
                open = false;
            }
            List<String> issues = preparation.issues(s, Map.of(child, person), now).get(child);
            return new FamilySession(s.id(), r.programme(), r.className(), r.room(), r.location(), r.timeZone(),
                s.startsAt(), s.endsAt(), s.status().name(), issues, open);
        }).toList(), page.size() > limit);
    }
    public FamilyPage<FamilyVisit> attendance(UUID guardian, UUID child, int limit, int offset) {
        require(guardian, child);
        ChildrenRules.page(limit, offset);
        List<MemberFamilyReader.FamilyAttendance> page =
            reader.attendance(guardian, child, clock.instant(), limit + 1, offset);
        return new FamilyPage<>(true, page.stream().limit(limit).map(v -> {
            UUID programme = participation.ministryClass(sessionClass(v.sessionId())).orElseThrow().programmeId();
            return new FamilyVisit(v,
                v.collectedAt() == null && canCollect(guardian, child, programme, clock.instant()));
        }).toList(), page.size() > limit);
    }
    private final SessionReader sessionReader;
    private UUID sessionClass(UUID id) {
        return sessionReader.ministrySession(id).orElseThrow().classId();
    }
    public boolean canCollect(UUID guardian, UUID child, UUID programme, Instant now) {
        return safeguarding.validAuthorizations(programme, child, guardian, now)
            .stream()
            .anyMatch(grant -> safeguarding.guardianAuthority(grant.authorityId())
                .filter(a -> a.covers(now, now))
                .isPresent());
    }
}
