package org.onechurch.identityaccess.application.command.logout;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.application.contract.SessionWriter;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;

public final class LogoutHandler {

    private final Authorization authorization;

    private final SessionWriter sessions;
    private final IdentityAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;

    public LogoutHandler(SessionWriter sessions, IdentityAuditWriter audit, UnitOfWork transactions, Clock clock,
        Authorization authorization) {
        this.authorization = authorization;
        this.sessions = sessions;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
    }

    public void handle(LogoutCommand command) {
        authorization.requireSession(command.accountId(), command.sessionId());
        transactions.execute(() -> {
            sessions.revoke(command.sessionId(), command.accountId());
            audit.audit(command.accountId(), "session.logged_out", command.accountId(), clock.instant());
            return null;
        });
    }
}
