package org.onechurch.childrenministry.participation.application.query.listprogrammechildren;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.application.contract.ProgrammeRosterReader;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.application.ChildReadiness;
import org.onechurch.childrenministry.safeguarding.application.ReadinessView;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
public final class ListProgrammeChildrenHandler {
    private final PermissionChecker authorization;
    private final ParticipationReader participation;
    private final ProgrammeRosterReader roster;
    private final PersonDirectory people;
    private final ChildReadiness readiness;
    private final Clock clock;
    private final ZoneId zone;
    public ListProgrammeChildrenHandler(PermissionChecker authorization, ParticipationReader participation,
        ProgrammeRosterReader roster, PersonDirectory people, Clock clock, ZoneId zone, ChildReadiness readiness) {
        this.authorization = authorization;
        this.participation = participation;
        this.roster = roster;
        this.people = people;
        this.clock = clock;
        this.zone = zone;
        this.readiness = readiness;
    }
    public ProgrammeChildrenPage handle(ListProgrammeChildrenQuery query) {
        authorization.requirePermission("children.read");
        ChildrenRules.page(query.limit(), query.offset());
        String search = ChildrenRules.optional(query.search(), 120);
        String status = query.status() == null ? "ALL" : query.status();
        if (!List.of("ALL", "ACTIVE", "UPCOMING", "EXPIRED", "ENDED").contains(status)) {
            throw ChildrenException.invalid("Choose a valid enrolment status.");
        }
        Programme programme =
            participation.programme(query.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
        LocalDate on = LocalDate.now(clock.withZone(zone));
        List<UUID> ids = search.isBlank() ? null : people.matchingIds(search);
        ProgrammeRosterReader.Page page =
            roster.list(query.programmeId(), ids, status, on, query.limit(), query.offset());
        Map<UUID, PersonDirectory.Entry> names =
            people.getAll(page.enrolments().stream().map(Enrolment::childId).distinct().toList())
                .stream()
                .collect(Collectors.toMap(PersonDirectory.Entry::id, Function.identity()));
        Map<UUID, List<ProgrammeChildrenPage.Placement>> placements =
            roster.placements(page.enrolments().stream().map(Enrolment::id).toList(), on);
        Map<UUID, ReadinessView> checks = readiness.assess(programme,
            page.enrolments().stream().filter(value -> value.covers(on)).map(Enrolment::childId).distinct().toList());
        return new ProgrammeChildrenPage(
            page.enrolments()
                .stream()
                .map(enrolment -> new ProgrammeChildrenPage.Child(enrolment.id(), enrolment.childId(),
                    names.containsKey(enrolment.childId()) ? names.get(enrolment.childId()).name()
                        : "Unavailable person",
                    enrolment.validFrom(), enrolment.validUntil(), status(enrolment, on),
                    placements.getOrDefault(enrolment.id(), List.of()),
                    enrolment.covers(on) ? checks.get(enrolment.childId()).summary() : null))
                .toList(),
            page.total());
    }
    private static String status(Enrolment enrolment, LocalDate on) {
        if (enrolment.endedAt() != null) {
            return "ENDED";
        }
        if (enrolment.validFrom().isAfter(on)) {
            return "UPCOMING";
        }
        return enrolment.validUntil().isAfter(on) ? "ACTIVE" : "EXPIRED";
    }
}
