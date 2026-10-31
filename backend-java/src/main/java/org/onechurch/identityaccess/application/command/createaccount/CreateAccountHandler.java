package org.onechurch.identityaccess.application.command.createaccount;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.application.contract.PasswordHasher;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.IdentityRules;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class CreateAccountHandler {

    private final Authorization authorization;

    private final IdentityAuditWriter audit;
    private final AccountRepository repository;
    private final PasswordHasher passwords;
    private final UnitOfWork transactions;
    private final Clock clock;

    public CreateAccountHandler(IdentityAuditWriter audit, AccountRepository repository, PasswordHasher passwords,
        UnitOfWork transactions, Clock clock, Authorization authorization) {
        this.authorization = authorization;
        this.audit = audit;
        this.repository = repository;
        this.passwords = passwords;
        this.transactions = transactions;
        this.clock = clock;
    }

    public UUID handle(CreateAccountCommand command) {
        authorization.requirePermission(command.actorId(), "identity.manage");
        String username = IdentityRules.username(command.username());
        String email = IdentityRules.email(command.email());
        String name = IdentityRules.name(command.displayName());
        IdentityRules.password(command.password());
        String hash = passwords.hash(command.password());
        return transactions.execute(() -> {
            UUID id = UUID.randomUUID();
            Instant now = clock.instant();
            repository.save(Account.register(id, username, email, name, hash, now));
            audit.audit(command.actorId(), "account.created", id, now);
            return id;
        });
    }
}
