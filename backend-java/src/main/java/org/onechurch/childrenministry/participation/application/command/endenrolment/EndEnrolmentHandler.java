package org.onechurch.childrenministry.participation.application.command.endenrolment;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.repository.ParticipationRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;

public final class EndEnrolmentHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipationReader participation;
    private final ParticipationRepository participationRepository;
    private final ParticipantAccess participants;
    public EndEnrolmentHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
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
    public void handle(EndEnrolmentCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        transactions.execute(() -> {
            Enrolment source = participation.enrolment(command.enrolmentId())
                .orElseThrow(() -> ChildrenException.missing("Enrolment"));
            participants.lockChild(source.childId());
            Instant now = clock.instant();
            Enrolment value = participationRepository.enrolmentForUpdate(command.enrolmentId())
                .orElseThrow(() -> ChildrenException.missing("Enrolment"));
            participationRepository.updateEnrolment(value.end(command.expectedVersion(), now));
            participationRepository.endAssignments(value.id(), now);
            audit.record(command.actorId(), "enrolment.ended", value.id(), "", now);
            return null;
        });
    }
}
