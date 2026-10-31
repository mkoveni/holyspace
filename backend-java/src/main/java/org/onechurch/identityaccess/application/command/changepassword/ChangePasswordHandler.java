package org.onechurch.identityaccess.application.command.changepassword;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.application.contract.PasswordHasher;
import org.onechurch.identityaccess.application.contract.SessionWriter;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.IdentityRules;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;

public final class ChangePasswordHandler {

    private final Authorization authorization;

    private final AccountRepository reader;
    private final IdentityAuditWriter audit;
    private final SessionWriter sessions;
    private final PasswordHasher passwords;
    private final UnitOfWork transactions;
    private final Clock clock;

    public ChangePasswordHandler(AccountRepository reader, IdentityAuditWriter audit, SessionWriter sessions,
        PasswordHasher passwords, UnitOfWork transactions, Clock clock, Authorization authorization) {
        this.authorization = authorization;
        this.reader = reader;
        this.audit = audit;
        this.sessions = sessions;
        this.passwords = passwords;
        this.transactions = transactions;
        this.clock = clock;
    }

    public void handle(ChangePasswordCommand command) {
        if (command.administratorReset()) {
            authorization.requirePermission(command.actorId(), "identity.manage");
        } else {
            authorization.requireSelf(command.actorId());
        }
        IdentityRules.password(command.password());
        transactions.execute(() -> {
            Account account = reader.findForUpdate(command.id())
                .orElseThrow(() -> new IdentityException(IdentityException.Kind.NOT_FOUND, "Account not found."));
            if (!command.administratorReset() && (!command.actorId().equals(command.id())
                || !passwords.matches(command.currentPassword(), account.passwordHash()))) {
                throw new IdentityException(IdentityException.Kind.FORBIDDEN, "Current password is incorrect.");
            }
            reader.save(account.changePassword(passwords.hash(command.password()), clock.instant()));
            sessions.revokeAll(command.id());
            audit.audit(command.actorId(), "account.password_changed", command.id(), clock.instant());
            return null;
        });
    }
}
