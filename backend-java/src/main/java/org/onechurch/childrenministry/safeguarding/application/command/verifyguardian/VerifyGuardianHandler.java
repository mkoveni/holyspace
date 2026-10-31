package org.onechurch.childrenministry.safeguarding.application.command.verifyguardian;

import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.UUID;

public final class VerifyGuardianHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final SafeguardingRepository safeguardingRepository;
    private final ZoneId zone;
    public VerifyGuardianHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipantAccess participants, SafeguardingRepository safeguardingRepository, ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participants = participants;
        this.safeguardingRepository = safeguardingRepository;
        this.zone = zone;
    }
    public UUID handle(VerifyGuardianCommand command) {
        authorization.requirePermission(command.actorId(), "children.safeguarding");
        return transactions.execute(() -> {
            participants.lockChild(command.childId(), command.guardianId());
            Instant now = clock.instant();
            LocalDate today = LocalDate.now(clock.withZone(zone));
            ChildrenRules.child(participants.available(command.childId()).birthDate(), today);
            ChildrenRules.adult(participants.available(command.guardianId()).birthDate(), today);
            GuardianAuthority authority =
                new GuardianAuthority(UUID.randomUUID(), command.childId(), command.guardianId(), command.validFrom(),
                    command.validUntil(), command.evidenceReference(), command.actorId(), null, null, 0);
            safeguardingRepository.insertGuardianAuthority(authority);
            audit.record(command.actorId(), "guardian.authority.verified", authority.id(), "", now);
            return authority.id();
        });
    }
}
