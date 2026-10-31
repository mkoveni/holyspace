package org.onechurch.childrenministry.safeguarding.application.command.publishconsentpolicy;

import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.participation.domain.repository.ParticipationRepository;
import org.onechurch.childrenministry.safeguarding.domain.ConsentPolicy;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class PublishConsentPolicyHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipationRepository participationRepository;
    public PublishConsentPolicyHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipationRepository participationRepository) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participationRepository = participationRepository;
    }
    public UUID handle(PublishConsentPolicyCommand command) {
        authorization.requirePermission(command.actorId(), "children.manage");
        return transactions.execute(() -> {
            Programme programme = participationRepository.programmeForUpdate(command.programmeId())
                .orElseThrow(() -> ChildrenException.missing("Programme"));
            ChildrenRules.version(programme.version(), command.expectedVersion());
            Instant now = clock.instant();
            ConsentPolicy policy = new ConsentPolicy(UUID.randomUUID(), programme.id(), command.label(),
                command.documentReference(), command.contentHash(), now);
            participationRepository.insertConsentPolicy(policy);
            participationRepository.updateProgramme(new Programme(programme.id(), programme.name(), policy.id(),
                programme.minStaff(), programme.childrenPerStaff(), programme.version() + 1, programme.createdAt()));
            audit.record(command.actorId(), "consent.policy.published", policy.id(), "", now);
            return policy.id();
        });
    }
}
