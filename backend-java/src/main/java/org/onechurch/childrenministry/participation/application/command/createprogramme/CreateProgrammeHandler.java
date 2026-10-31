package org.onechurch.childrenministry.participation.application.command.createprogramme;

import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.participation.domain.repository.ParticipationRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class CreateProgrammeHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipationRepository participationRepository;
    public CreateProgrammeHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipationRepository participationRepository) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participationRepository = participationRepository;
    }
    public UUID handle(CreateProgrammeCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        return transactions.execute(() -> {
            Instant now = clock.instant();
            Programme programme = new Programme(UUID.randomUUID(), command.name(), null, command.minStaff(),
                command.childrenPerStaff(), 0, now);
            participationRepository.insertProgramme(programme);
            audit.record(command.actorId(), "programme.created", programme.id(), "", now);
            return programme.id();
        });
    }
}
