package org.onechurch.childrenministry.safeguarding.application;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.application.contract.ReadinessReader;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.Collection;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
import java.util.stream.Stream;
/** A programme preparation snapshot, never a substitute for session admission checks. */
public final class ChildReadiness {
    private final ReadinessReader reader;
    private final PersonDirectory people;
    private final ParticipationReader participation;
    private final Clock clock;
    private final ZoneId zone;
    public ChildReadiness(ReadinessReader reader, PersonDirectory people, ParticipationReader participation,
        Clock clock, ZoneId zone) {
        this.reader = reader;
        this.people = people;
        this.participation = participation;
        this.clock = clock;
        this.zone = zone;
    }
    public Map<UUID, ReadinessView> assess(Programme programme, Collection<UUID> childIds) {
        if (childIds.isEmpty()) {
            return Map.of();
        }
        Instant at = ChildrenRules.instant(clock.instant());
        LocalDate on = at.atZone(zone).toLocalDate();
        ReadinessReader.Facts facts = reader.load(programme.id(), programme.currentPolicyId(), childIds, on, at);
        List<UUID> personIds =
            Stream.concat(childIds.stream(), facts.collectors().stream().map(value -> value.collectorId()))
                .distinct()
                .toList();
        Map<UUID, PersonDirectory.Entry> persons =
            people.getAll(personIds).stream().collect(Collectors.toMap(PersonDirectory.Entry::id, Function.identity()));
        List<MinistryClass> classes = participation.classes(programme.id());
        return childIds.stream()
            .distinct()
            .collect(
                Collectors.toMap(Function.identity(), id -> assess(id, programme, facts, persons, classes, on, at)));
    }
    private ReadinessView assess(UUID childId, Programme programme, ReadinessReader.Facts facts,
        Map<UUID, PersonDirectory.Entry> persons, List<MinistryClass> classes, LocalDate on, Instant at) {
        PersonDirectory.Entry child = persons.get(childId);
        boolean eligible = child != null && child.available() && child.birthDate() != null
            && !child.birthDate().isAfter(on) && child.birthDate().plusYears(18).isAfter(on);
        Enrolment enrolment =
            facts.enrolments().stream().filter(value -> value.childId().equals(childId)).findFirst().orElse(null);
        List<UUID> authorities = facts.guardians()
            .stream()
            .filter(value -> value.childId().equals(childId))
            .map(GuardianAuthority::id)
            .toList();
        boolean placed = eligible && enrolment != null
            && facts.assignments()
                .stream()
                .filter(value -> value.childId().equals(childId) && value.enrolmentId().equals(enrolment.id()))
                .anyMatch(value -> classes.stream()
                    .anyMatch(group -> group.id().equals(value.classId())
                        && !child.birthDate().plusMonths(group.minAgeMonths()).isAfter(on)
                        && child.birthDate().plusMonths(group.maxAgeMonths()).isAfter(on)));
        boolean consent = facts.consents()
            .stream()
            .anyMatch(value -> value.childId().equals(childId) && authorities.contains(value.authorityId()));
        boolean collector = facts.collectors()
            .stream()
            .filter(value -> value.childId().equals(childId) && authorities.contains(value.authorityId()))
            .map(value -> persons.get(value.collectorId()))
            .anyMatch(person -> person != null && person.available() && person.birthDate() != null
                && !person.birthDate().plusYears(18).isAfter(on));
        List<ReadinessView.Step> steps = List.of(new ReadinessView.Step("IDENTITY", eligible),
            new ReadinessView.Step("ENROLMENT", enrolment != null), new ReadinessView.Step("CLASS", placed),
            new ReadinessView.Step("POLICY", programme.currentPolicyId() != null),
            new ReadinessView.Step("GUARDIAN", !authorities.isEmpty()), new ReadinessView.Step("CONSENT", consent),
            new ReadinessView.Step("COLLECTOR", collector));
        int completed = (int) steps.stream().filter(ReadinessView.Step::complete).count();
        return new ReadinessView(completed == steps.size(), completed, steps.size(), on, at, steps, enrolment,
            zone.getId());
    }
}
