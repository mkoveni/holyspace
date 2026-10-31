package org.onechurch.identityaccess.application.command.revokemysession;
import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccountIdentityGuard;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.SessionWriter;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
public final class RevokeMySessionHandler {
    private final Authorization auth;
    private final AccountIdentityGuard guard;
    private final SessionReader reader;
    private final SessionWriter writer;
    private final IdentityAuditWriter audit;
    private final UnitOfWork tx;
    private final Clock clock;
    public RevokeMySessionHandler(Authorization auth, AccountIdentityGuard guard, SessionReader reader,
        SessionWriter writer, IdentityAuditWriter audit, UnitOfWork tx, Clock clock) {
        this.auth = auth;
        this.guard = guard;
        this.reader = reader;
        this.writer = writer;
        this.audit = audit;
        this.tx = tx;
        this.clock = clock;
    }
    public void handle(RevokeMySessionCommand c) {
        auth.requireSelf(c.actorId());
        tx.execute(() -> {
            guard.lock(c.actorId());
            auth.requireSelf(c.actorId());
            reader.findById(c.sessionId())
                .filter(s -> s.accountId().equals(c.actorId()))
                .orElseThrow(() -> new IdentityException(IdentityException.Kind.NOT_FOUND, "Session was not found."));
            writer.revoke(c.sessionId(), c.actorId());
            audit.audit(c.actorId(), "session.revoked", c.sessionId(), clock.instant());
            return null;
        });
    }
}
