package org.onechurch.childrenministry.participation.application.command.enrolchild;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.repository.ParticipationRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.UUID;

public final class EnrolChildHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipationRepository participationRepository;
    private final ParticipationReader participation;
    private final ParticipantAccess participants;
    private final ZoneId zone;
    public EnrolChildHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions, Clock clock,
        ParticipationRepository participationRepository, ParticipationReader participation,
        ParticipantAccess participants, ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participationRepository = participationRepository;
        this.participation = participation;
        this.participants = participants;
        this.zone = zone;
    }
    public UUID handle(EnrolChildCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        return transactions.execute(() -> {
            participants.lockChild(command.childId());
            Instant now = clock.instant();
            participation.programme(command.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
            ChildrenRules.child(participants.available(command.childId()).birthDate(),
                LocalDate.now(clock.withZone(zone)));
            Enrolment enrolment = new Enrolment(UUID.randomUUID(), command.programmeId(), command.childId(),
                command.validFrom(), command.validUntil(), null, 0, now);
            participationRepository.insertEnrolment(enrolment);
            audit.record(command.actorId(), "child.enrolled", enrolment.id(), "", now);
            return enrolment.id();
        });
    }
}
