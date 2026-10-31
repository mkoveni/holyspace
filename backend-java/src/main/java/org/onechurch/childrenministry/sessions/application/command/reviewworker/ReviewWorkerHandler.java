package org.onechurch.childrenministry.sessions.application.command.reviewworker;

import org.onechurch.childrenministry.sessions.application.ParticipantAccess;
import org.onechurch.childrenministry.sessions.domain.Worker;
import org.onechurch.childrenministry.sessions.domain.repository.SessionRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;

public final class ReviewWorkerHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final SessionRepository sessionRepository;
    private final ParticipantAccess participants;
    private final ZoneId zone;
    private final AccountDirectory accounts;
    public ReviewWorkerHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, SessionRepository sessionRepository, ParticipantAccess participants, ZoneId zone,
        AccountDirectory accounts) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.sessionRepository = sessionRepository;
        this.participants = participants;
        this.zone = zone;
        this.accounts = accounts;
    }
    public void handle(ReviewWorkerCommand command) {
        authorization.requirePermission(command.actorId(), "children.safeguarding");
        transactions.execute(() -> {
            participants.lockChild(command.personId());
            Worker value = sessionRepository.workerForUpdate(command.personId())
                .orElseThrow(() -> ChildrenException.missing("Worker"));
            ChildrenRules.version(value.version(), command.expectedVersion());
            Instant now = clock.instant();
            if (!command.revoked()) {
                ChildrenRules.adult(participants.available(command.personId()).birthDate(),
                    LocalDate.now(clock.withZone(zone)));
                if (!accounts.active(value.accountId())) {
                    throw ChildrenException.invalid("Worker account is inactive.");
                }
            }
            sessionRepository.updateWorker(new Worker(value.personId(), value.accountId(),
                command.revoked() ? value.verifiedFrom() : now, command.screenedUntil(), command.trainedUntil(),
                command.evidenceReference(), command.actorId(), command.revoked() ? now : null, value.version() + 1));
            audit.record(command.actorId(), "worker.reviewed", value.personId(), "", now);
            return null;
        });
    }
}
