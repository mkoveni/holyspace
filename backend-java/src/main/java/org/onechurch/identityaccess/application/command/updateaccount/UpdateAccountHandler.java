package org.onechurch.identityaccess.application.command.updateaccount;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.IdentityRules;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;

public final class UpdateAccountHandler {

    private final Authorization authorization;

    private final AccountRepository reader;
    private final IdentityAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;

    public UpdateAccountHandler(AccountRepository reader, IdentityAuditWriter audit, UnitOfWork transactions,
        Clock clock, Authorization authorization) {
        this.authorization = authorization;
        this.reader = reader;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
    }

    public void handle(UpdateAccountCommand command) {
        authorization.requirePermission(command.actorId(), "identity.manage");
        String email = IdentityRules.email(command.email());
        String name = IdentityRules.name(command.displayName());
        transactions.execute(() -> {
            Account account = reader.findForUpdate(command.id())
                .map(found -> found.updateProfile(email, name, clock.instant()))
                .orElseThrow(() -> new IdentityException(IdentityException.Kind.NOT_FOUND, "Account not found."));
            reader.save(account);
            audit.audit(command.actorId(), "account.updated", command.id(), clock.instant());
            return null;
        });
    }
}
