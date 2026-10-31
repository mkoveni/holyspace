package org.onechurch.childrenministry.sessions.application.command.registerworker;

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

public final class RegisterWorkerHandler {
    private final PermissionChecker authorization;
    private final ChildrenAudit audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final ParticipantAccess participants;
    private final AccountDirectory accounts;
    private final SessionRepository sessionRepository;
    private final ZoneId zone;
    public RegisterWorkerHandler(PermissionChecker authorization, ChildrenAudit audit, UnitOfWork transactions,
        Clock clock, ParticipantAccess participants, AccountDirectory accounts, SessionRepository sessionRepository,
        ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.participants = participants;
        this.accounts = accounts;
        this.sessionRepository = sessionRepository;
        this.zone = zone;
    }
    public void handle(RegisterWorkerCommand command) {
        authorization.requirePermission(command.actorId(), "children.safeguarding");
        transactions.execute(() -> {
            participants.lockChild(command.personId());
            Instant now = clock.instant();
            ChildrenRules.adult(participants.available(command.personId()).birthDate(),
                LocalDate.now(clock.withZone(zone)));
            if (!accounts.active(command.accountId())) {
                throw ChildrenException.invalid("Link an active account to the verified worker.");
            }
            sessionRepository
                .insertWorker(new Worker(command.personId(), command.accountId(), now, command.screenedUntil(),
                    command.trainedUntil(), command.evidenceReference(), command.actorId(), null, 0));
            audit.record(command.actorId(), "worker.verified", command.personId(), "", now);
            return null;
        });
    }
}
