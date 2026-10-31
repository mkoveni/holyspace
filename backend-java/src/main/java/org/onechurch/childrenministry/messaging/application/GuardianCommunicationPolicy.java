package org.onechurch.childrenministry.messaging.application;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.sessions.application.contract.SessionReader;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.time.format.DateTimeFormatter;
import java.util.Map;
import java.util.UUID;
public final class GuardianCommunicationPolicy {
    private final SafeguardingReader safeguarding;
    private final ParticipationReader participation;
    private final SessionReader sessions;
    private final PersonDirectory people;
    private final ZoneId zone;
    public GuardianCommunicationPolicy(SafeguardingReader safeguarding, ParticipationReader participation,
        SessionReader sessions, PersonDirectory people, ZoneId zone) {
        this.safeguarding = safeguarding;
        this.participation = participation;
        this.sessions = sessions;
        this.people = people;
        this.zone = zone;
    }
    public GuardianAuthority guardian(UUID child, UUID authorityId, Instant now) {
        GuardianAuthority authority = safeguarding.guardianAuthority(authorityId)
            .filter(a -> a.childId().equals(child) && a.covers(now, now))
            .orElseThrow(() -> ChildrenException
                .conflict("Current verified guardian authority is required before preparing or recording contact."));
        PersonDirectory.Entry person = people.get(authority.guardianId())
            .filter(PersonDirectory.Entry::available)
            .orElseThrow(() -> ChildrenException.conflict("The guardian is unavailable."));
        ChildrenRules.adult(person.birthDate(), now.atZone(zone).toLocalDate());
        return authority;
    }
    public Programme programme(UUID programme, UUID child, String type, UUID sessionId, Instant now) {
        Programme p = participation.programme(programme).orElseThrow(() -> ChildrenException.missing("Programme"));
        LocalDate on = now.atZone(zone).toLocalDate();
        PersonDirectory.Entry identity = people.get(child)
            .filter(PersonDirectory.Entry::available)
            .orElseThrow(() -> ChildrenException.conflict("The child is unavailable."));
        ChildrenRules.child(identity.birthDate(), on);
        if (type.equals("CONSENT_RENEWAL")) {
            if (sessionId != null || p.currentPolicyId() == null) {
                throw ChildrenException
                    .invalid("Publish a consent policy and omit a session for a consent-renewal request.");
            }
        } else if (type.equals("SESSION_REMINDER")) {
            MinistrySession session = sessions.ministrySession(sessionId)
                .filter(s -> s.startsAt().isAfter(now) && s.status() != SessionStatus.CLOSED
                    && s.status() != SessionStatus.CANCELLED)
                .orElseThrow(() -> ChildrenException.conflict("Choose an upcoming active session."));
            if (participation.ministryClass(session.classId())
                .filter(c -> c.programmeId().equals(programme))
                .isEmpty()) {
                throw ChildrenException.invalid("The session belongs to another programme.");
            }
            on = session.startsAt().atZone(zone).toLocalDate();
            ChildrenRules.child(identity.birthDate(), on);
            final LocalDate date = on;
            if (participation.assignmentAt(session.classId(), child, date)
                .filter(a -> participation.enrolment(a.enrolmentId()).filter(e -> e.covers(date)).isPresent())
                .isEmpty()) {
                throw ChildrenException.conflict("The child needs a current placement covering this session.");
            }
        } else
            throw ChildrenException.invalid("Choose a communication type.");
        if (participation.enrolmentAt(programme, child, on).isEmpty()) {
            throw ChildrenException.conflict("An active programme enrolment is required.");
        }
        return p;
    }
    public String message(UUID child, UUID guardian, Programme programme, String type, UUID sessionId) {
        String childName = people.get(child).orElseThrow().name();
        String guardianName = people.get(guardian).orElseThrow().name();
        if (type.equals("CONSENT_RENEWAL")) {
            String label = participation.consentPolicy(programme.currentPolicyId())
                .orElseThrow(() -> ChildrenException.missing("Consent policy"))
                .label();
            return "Hello " + guardianName + ", please contact the ministry team to review and renew consent for "
                + childName + " in " + programme.name() + " (policy: " + label
                + "). Consent must be recorded by an authorized coordinator before attendance. Thank you.";
        }
        MinistrySession session = sessions.ministrySession(sessionId).orElseThrow();
        return "Hello " + guardianName + ", a reminder that " + childName + " is scheduled for " + programme.name()
            + " on " + DateTimeFormatter.ofPattern("dd MMM uuuu 'at' HH:mm VV").format(session.startsAt().atZone(zone))
            + ". Please speak to the ministry team about preparation and collection arrangements. This reminder does not confirm admission or pickup authority. Thank you.";
    }
    public Map<String, String> variables(GuardianCommunication communication) {
        Programme programme = participation.programme(communication.programmeId()).orElseThrow();
        String sessionDate = communication.sessionId() == null ? ""
            : sessions.ministrySession(communication.sessionId())
                .map(session -> DateTimeFormatter.ofPattern("dd MMM uuuu 'at' HH:mm VV")
                    .format(session.startsAt().atZone(zone)))
                .orElse("");
        String policyName = communication.policyId() == null ? ""
            : participation.consentPolicy(communication.policyId()).map(value -> value.label()).orElse("");
        return Map.of("message", communication.message(), "guardianName",
            people.get(communication.guardianId()).orElseThrow().name(), "childName",
            people.get(communication.childId()).orElseThrow().name(), "programmeName", programme.name(), "sessionDate",
            sessionDate, "policyName", policyName);
    }
    public void contactDate(UUID sessionId, LocalDate dueOn) {
        if (sessionId != null && sessions.ministrySession(sessionId)
            .filter(session -> dueOn.isAfter(session.startsAt().atZone(zone).toLocalDate()))
            .isPresent()) {
            throw ChildrenException.invalid("The reminder contact date must not follow the session date.");
        }
    }

}
