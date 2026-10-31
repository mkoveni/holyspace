package org.onechurch.childrenministry.safeguarding.application.command.revokeconsent;

import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.Consent;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;

public final class RevokeConsentHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final SafeguardingReader safeguarding;
    private final SafeguardingRepository safeguardingRepository;
    public RevokeConsentHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
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
    public void handle(RevokeConsentCommand command) {
        authorization.requirePermission(command.actorId(), "children.safeguarding");
        transactions.execute(() -> {
            Consent source = safeguarding.consent(command.id()).orElseThrow(() -> ChildrenException.missing("Consent"));
            participants.lockChild(source.childId());
            Instant now = clock.instant();
            Consent value = safeguardingRepository.consentForUpdate(command.id())
                .orElseThrow(() -> ChildrenException.missing("Consent"));
            safeguardingRepository.updateConsent(value.revoke(command.expectedVersion(), command.reason(), now));
            audit.record(command.actorId(), "consent.revoked", value.id(), ChildrenRules.text(command.reason(), 500),
                now);
            return null;
        });
    }
}
