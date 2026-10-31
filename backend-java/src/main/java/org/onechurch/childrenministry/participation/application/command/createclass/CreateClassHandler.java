package org.onechurch.childrenministry.participation.application.command.createclass;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.repository.ParticipationRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class CreateClassHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipationRepository participationRepository;
    private final ParticipationReader participation;
    public CreateClassHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipationRepository participationRepository, ParticipationReader participation) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participationRepository = participationRepository;
        this.participation = participation;
    }
    public UUID handle(CreateClassCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        return transactions.execute(() -> {
            participation.programme(command.programmeId()).orElseThrow(() -> ChildrenException.missing("Programme"));
            Instant now = clock.instant();
            MinistryClass group = new MinistryClass(UUID.randomUUID(), command.programmeId(), command.name(),
                command.minAgeMonths(), command.maxAgeMonths());
            participationRepository.insertMinistryClass(group);
            audit.record(command.actorId(), "class.created", group.id(), "", now);
            return group.id();
        });
    }
}
