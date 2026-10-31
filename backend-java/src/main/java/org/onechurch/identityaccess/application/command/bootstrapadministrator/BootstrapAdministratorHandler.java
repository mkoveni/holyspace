package org.onechurch.identityaccess.application.command.bootstrapadministrator;

import org.onechurch.identityaccess.application.contract.AccessWriter;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.application.contract.PasswordHasher;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.IdentityRules;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class BootstrapAdministratorHandler {

    private final AccountReader reader;
    private final AccessWriter writer;
    private final IdentityAuditWriter audit;
    private final AccountRepository repository;
    private final PasswordHasher passwords;
    private final UnitOfWork transactions;
    private final Clock clock;

    public BootstrapAdministratorHandler(AccountReader reader, AccessWriter writer, IdentityAuditWriter audit,
        AccountRepository repository, PasswordHasher passwords, UnitOfWork transactions, Clock clock) {
        this.reader = reader;
        this.writer = writer;
        this.audit = audit;
        this.repository = repository;
        this.passwords = passwords;
        this.transactions = transactions;
        this.clock = clock;
    }

    public UUID handle(BootstrapAdministratorCommand command) {
        String username = IdentityRules.username(command.username());
        String email = IdentityRules.email(command.email());
        String name = IdentityRules.name(command.displayName());
        IdentityRules.password(command.password());
        String hash = passwords.hash(command.password());
        return transactions.execute(() -> {
            writer.lockAccessChanges();
            if (reader.any()) {
                throw new IdentityException(IdentityException.Kind.CONFLICT,
                    "Administrator bootstrap is only allowed before the first account exists.");
            }
            UUID id = UUID.randomUUID();
            Instant now = clock.instant();
            repository.save(Account.register(id, username, email, name, hash, now));
            writer.assignRole(id, UUID.fromString("00000000-0000-0000-0000-000000000001"), true);
            audit.audit(null, "administrator.bootstrapped", id, now);
            return id;
        });
    }
}
