package org.onechurch.identityaccess.application.command.setaccountstatus;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccessReader;
import org.onechurch.identityaccess.application.contract.AccessWriter;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.application.contract.SessionWriter;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.AccountManagementPolicy;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;

public final class SetAccountStatusHandler {

    private final Authorization authorization;

    private final AccountRepository reader;
    private final AccessReader access;
    private final AccessWriter writer;
    private final IdentityAuditWriter audit;
    private final SessionWriter sessions;
    private final UnitOfWork transactions;
    private final Clock clock;

    public SetAccountStatusHandler(AccountRepository reader, AccessReader access, AccessWriter writer,
        IdentityAuditWriter audit, SessionWriter sessions, UnitOfWork transactions, Clock clock,
        Authorization authorization) {
        this.authorization = authorization;
        this.reader = reader;
        this.access = access;
        this.writer = writer;
        this.audit = audit;
        this.sessions = sessions;
        this.transactions = transactions;
        this.clock = clock;
    }

    public void handle(SetAccountStatusCommand command) {
        transactions.execute(() -> {
            writer.lockAccessChanges();
            authorization.requirePermission(command.actorId(), "identity.manage");
            Account account = reader.findForUpdate(command.id())
                .map(found -> command.enabled() ? found.enable(clock.instant()) : found.disable(clock.instant()))
                .orElseThrow(() -> new IdentityException(IdentityException.Kind.NOT_FOUND, "Account not found."));
            reader.save(account);
            AccountManagementPolicy.requireActiveManager(access.activeManagers());
            if (!command.enabled()) {
                sessions.revokeAll(command.id());
            }
            audit.audit(command.actorId(), command.enabled() ? "account.enabled" : "account.disabled", command.id(),
                clock.instant());
            return null;
        });
    }
}
