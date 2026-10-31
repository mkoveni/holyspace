package org.onechurch.childrenministry.sessions.application;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.Consent;
import org.onechurch.childrenministry.sessions.domain.MinistrySession;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
public final class AdmissionPolicy {
    public record Evidence(UUID enrolmentId, UUID assignmentId, UUID consentId) {}
    private final ParticipationReader participation;
    private final SafeguardingReader safeguarding;
    private final ParticipantAccess participants;
    private final SessionOperations sessions;
    public AdmissionPolicy(ParticipationReader participation, SafeguardingReader safeguarding,
        ParticipantAccess participants, SessionOperations sessions) {
        this.participation = participation;
        this.safeguarding = safeguarding;
        this.participants = participants;
        this.sessions = sessions;
    }
    public Evidence require(MinistrySession session, UUID childId, Instant now) {
        MinistryClass group =
            participation.ministryClass(session.classId()).orElseThrow(() -> ChildrenException.missing("Class"));
        Programme programme =
            participation.programme(group.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
        LocalDate on = sessions.date(session);
        PersonDirectory.Entry child = participants.available(childId);
        group.requireAge(child.birthDate(), on);
        Enrolment enrolment = participation.enrolmentAt(programme.id(), childId, on)
            .orElseThrow(
                () -> ChildrenException.conflict("Active programme enrolment is required for the session date."));
        ClassAssignment assignment = participation.assignmentAt(group.id(), childId, on)
            .filter(value -> value.enrolmentId().equals(enrolment.id()))
            .orElseThrow(
                () -> ChildrenException.conflict("A current class assignment is required for the session date."));
        Consent consent =
            safeguarding.validConsents(programme.id(), programme.currentPolicyId(), childId, now, session.endsAt())
                .stream()
                .filter(value -> safeguarding.guardianAuthority(value.authorityId())
                    .filter(authority -> authority.covers(now, session.endsAt()))
                    .isPresent())
                .findFirst()
                .orElseThrow(() -> ChildrenException
                    .conflict("Current guardian consent for the programme policy must cover the session."));
        return new Evidence(enrolment.id(), assignment.id(), consent.id());
    }
}
