package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.application.contract.ReadinessReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Instant;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.function.Function;
import java.util.stream.Collectors;
public final class SessionChildPreparation {
    private final ReadinessReader reader;
    private final ParticipationReader participation;
    private final SessionOperations operations;
    public SessionChildPreparation(ReadinessReader reader, ParticipationReader participation,
        SessionOperations operations) {
        this.reader = reader;
        this.participation = participation;
        this.operations = operations;
    }
    public Map<UUID, List<String>> issues(MinistrySession session, Map<UUID, PersonDirectory.Entry> children,
        Instant now) {
        if (children.isEmpty()) {
            return Map.of();
        }
        MinistryClass group =
            participation.ministryClass(session.classId()).orElseThrow(() -> ChildrenException.missing("Class"));
        Programme programme =
            participation.programme(group.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
        Instant from =
            session.status() == SessionStatus.PLANNED && session.startsAt().isAfter(now) ? session.startsAt() : now;
        LocalDate on = operations.date(session);
        ReadinessReader.Facts facts =
            reader.load(programme.id(), programme.currentPolicyId(), children.keySet(), on, from);
        return children.keySet()
            .stream()
            .collect(Collectors.toMap(Function.identity(),
                id -> issues(id, children.get(id), group, session, facts, on, from)));
    }
    private List<String> issues(UUID id, PersonDirectory.Entry child, MinistryClass group, MinistrySession session,
        ReadinessReader.Facts facts, LocalDate on, Instant from) {
        List<String> issues = new ArrayList<>();
        if (!child.available()) {
            issues.add("Child details need review before arrival.");
        }
        try {
            group.requireAge(child.birthDate(), on);
        } catch (ChildrenException exception) {
            issues.add(exception.getMessage());
        }
        Enrolment enrolment =
            facts.enrolments().stream().filter(value -> value.childId().equals(id)).findFirst().orElse(null);
        if (enrolment == null) {
            issues.add("Current programme enrolment is required for this session date.");
        }
        if (enrolment == null || facts.assignments()
            .stream()
            .noneMatch(value -> value.childId().equals(id) && value.enrolmentId().equals(enrolment.id())
                && value.classId().equals(group.id()))) {
            issues.add("Current class placement is required for this session date.");
        }
        boolean consent = facts.consents()
            .stream()
            .filter(value -> value.childId().equals(id) && value.covers(from, session.endsAt()))
            .anyMatch(value -> facts.guardians()
                .stream()
                .anyMatch(authority -> authority.id().equals(value.authorityId()) && authority.childId().equals(id)
                    && authority.covers(from, session.endsAt())));
        if (!consent) {
            issues.add("Guardian consent for the current policy must cover this session through its end.");
        }
        return List.copyOf(issues);
    }
}
