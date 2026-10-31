package org.onechurch.identityaccess.application.command.createrole;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccessWriter;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.IdentityRules;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;

public final class CreateRoleHandler {

    private final Authorization authorization;

    private final AccessWriter writer;
    private final IdentityAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;

    public CreateRoleHandler(AccessWriter writer, IdentityAuditWriter audit, UnitOfWork transactions, Clock clock,
        Authorization authorization) {
        this.authorization = authorization;
        this.writer = writer;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
    }

    public UUID handle(CreateRoleCommand command) {
        authorization.requirePermission(command.actorId(), "identity.manage");
        String value = IdentityRules.normalize(command.name());
        if (!value.matches("[a-z0-9][a-z0-9._-]{1,79}") || value.length() > 100) {
            throw new IdentityException(IdentityException.Kind.INVALID, "Invalid name.");
        }
        String description = command.description() == null ? "" : command.description().strip();
        if (description.length() > 255) {
            throw new IdentityException(IdentityException.Kind.INVALID, "Description must be at most 255 characters.");
        }
        return transactions.execute(() -> {
            UUID id = UUID.randomUUID();
            writer.createRole(id, value, description);
            audit.audit(command.actorId(), "role.created", id, clock.instant());
            return id;
        });
    }
}
