package org.onechurch.childrenministry.participation.application.command.assignclass;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.repository.ParticipationRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class AssignClassHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipationReader participation;
    private final ParticipationRepository participationRepository;
    private final ParticipantAccess participants;
    public AssignClassHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipationReader participation, ParticipationRepository participationRepository,
        ParticipantAccess participants) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participation = participation;
        this.participationRepository = participationRepository;
        this.participants = participants;
    }
    public UUID handle(AssignClassCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        return transactions.execute(() -> {
            Enrolment source = participation.enrolment(command.enrolmentId())
                .orElseThrow(() -> ChildrenException.missing("Enrolment"));
            participants.lockChild(source.childId());
            Enrolment enrolment = participationRepository.enrolmentForUpdate(command.enrolmentId())
                .orElseThrow(() -> ChildrenException.missing("Enrolment"));
            MinistryClass group = participation.ministryClass(command.classId())
                .orElseThrow(() -> ChildrenException.missing("MinistryClass"));
            if (enrolment.endedAt() != null || !enrolment.programmeId().equals(group.programmeId())
                || command.validFrom().isBefore(enrolment.validFrom())
                || command.validUntil().isAfter(enrolment.validUntil())) {
                throw ChildrenException.invalid("Class assignment must belong to and fit within an active enrolment.");
            }
            group.requireAge(participants.available(enrolment.childId()).birthDate(), command.validFrom());
            Instant now = clock.instant();
            ClassAssignment assignment = new ClassAssignment(UUID.randomUUID(), group.programmeId(), group.id(),
                enrolment.childId(), enrolment.id(), command.validFrom(), command.validUntil(), null, 0);
            participationRepository.insertClassAssignment(assignment);
            audit.record(command.actorId(), "class.assigned", assignment.id(), "", now);
            return assignment.id();
        });
    }
}
