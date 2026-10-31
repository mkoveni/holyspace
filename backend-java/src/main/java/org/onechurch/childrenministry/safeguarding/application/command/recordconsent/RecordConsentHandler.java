package org.onechurch.childrenministry.safeguarding.application.command.recordconsent;

import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.Consent;
import org.onechurch.childrenministry.safeguarding.domain.ConsentPolicy;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class RecordConsentHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final SafeguardingReader safeguarding;
    private final SafeguardingRepository safeguardingRepository;
    private final ParticipationReader participation;
    public RecordConsentHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipantAccess participants, SafeguardingReader safeguarding,
        SafeguardingRepository safeguardingRepository, ParticipationReader participation) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participants = participants;
        this.safeguarding = safeguarding;
        this.safeguardingRepository = safeguardingRepository;
        this.participation = participation;
    }
    public UUID handle(RecordConsentCommand command) {
        authorization.requirePermission(command.actorId(), "children.safeguarding");
        return transactions.execute(() -> {
            participants.lockChild(command.childId());
            Instant now = clock.instant();
            GuardianAuthority authority = safeguarding.guardianAuthority(command.authorityId())
                .orElseThrow(() -> ChildrenException.missing("GuardianAuthority"));
            ConsentPolicy policy = participation.consentPolicy(command.policyId())
                .orElseThrow(() -> ChildrenException.missing("ConsentPolicy"));
            if (!authority.childId().equals(command.childId())
                || !authority.covers(command.validFrom(), command.validUntil())
                || !policy.programmeId().equals(command.programmeId()) || command.acceptedAt() == null
                || command.acceptedAt().isAfter(now) || command.acceptedAt().isBefore(policy.createdAt())
                || !authority.covers(command.acceptedAt(), command.acceptedAt())) {
                throw ChildrenException.invalid(
                    "Consent requires verified authority, the correct policy, and evidenced acceptance within its authority period.");
            }
            Consent consent = new Consent(UUID.randomUUID(), command.programmeId(), policy.id(), command.childId(),
                authority.id(), command.validFrom(), command.validUntil(), command.acceptedAt(), command.actorId(),
                command.evidenceReference(), null, null, 0);
            safeguardingRepository.insertConsent(consent);
            audit.record(command.actorId(), "consent.recorded", consent.id(), "", now);
            return consent.id();
        });
    }
}
