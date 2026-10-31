package org.onechurch.childrenministry.safeguarding.application.command.revokeguardian;

import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;

public final class RevokeGuardianHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final SafeguardingReader safeguarding;
    private final SafeguardingRepository safeguardingRepository;
    public RevokeGuardianHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipantAccess participants, SafeguardingReader safeguarding,
        SafeguardingRepository safeguardingRepository) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participants = participants;
        this.safeguarding = safeguarding;
        this.safeguardingRepository = safeguardingRepository;
    }
    public void handle(RevokeGuardianCommand command) {
        authorization.requirePermission(command.actorId(), "children.safeguarding");
        transactions.execute(() -> {
            GuardianAuthority source = safeguarding.guardianAuthority(command.id())
                .orElseThrow(() -> ChildrenException.missing("GuardianAuthority"));
            participants.lockChild(source.childId());
            Instant now = clock.instant();
            GuardianAuthority value = safeguardingRepository.guardianAuthorityForUpdate(command.id())
                .orElseThrow(() -> ChildrenException.missing("GuardianAuthority"));
            safeguardingRepository
                .updateGuardianAuthority(value.revoke(command.expectedVersion(), command.reason(), now));
            audit.record(command.actorId(), "guardianauthority.revoked", value.id(),
                ChildrenRules.text(command.reason(), 500), now);
            return null;
        });
    }
}
