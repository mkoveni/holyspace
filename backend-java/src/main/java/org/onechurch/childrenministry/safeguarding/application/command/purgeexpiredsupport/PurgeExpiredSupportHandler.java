package org.onechurch.childrenministry.safeguarding.application.command.purgeexpiredsupport;

import org.onechurch.childrenministry.safeguarding.application.contract.SupportReader;
import org.onechurch.childrenministry.safeguarding.domain.repository.SupportRepository;
import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.List;
import java.util.UUID;

public final class PurgeExpiredSupportHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final SupportReader support;
    private final SupportRepository supportRepository;
    private final ParticipantAccess participants;
    private final ZoneId zone;
    public PurgeExpiredSupportHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, SupportReader support, SupportRepository supportRepository, ParticipantAccess participants,
        ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.support = support;
        this.supportRepository = supportRepository;
        this.participants = participants;
        this.zone = zone;
    }
    public int handle(PurgeExpiredSupportCommand command) {
        authorization.requirePermission(command.actorId(), "children.support");
        authorization.requirePermission(command.actorId(), "children.supervise");
        return transactions.execute(() -> {
            ChildrenRules.page(command.limit(), 0);
            Instant now = clock.instant();
            LocalDate today = LocalDate.now(clock.withZone(zone));
            List<UUID> expired = support.expiredSupport(today, command.limit());
            int count = 0;
            for (UUID childId : expired) {
                participants.lockChild(childId);
                boolean stillExpired = supportRepository.supportRecordForUpdate(childId)
                    .filter(value -> value.retainUntil().isBefore(today))
                    .isPresent();
                if (!stillExpired) {
                    continue;
                }
                supportRepository.purge(childId, today);
                count++;
                audit.record(command.actorId(), "support.purged", childId, "Retention expired", now);
            }
            return count;
        });
    }
}
