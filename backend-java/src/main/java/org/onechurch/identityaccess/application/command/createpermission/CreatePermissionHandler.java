package org.onechurch.identityaccess.application.command.createpermission;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccessWriter;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.IdentityRules;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;

public final class CreatePermissionHandler {

    private final Authorization authorization;

    private final AccessWriter writer;
    private final IdentityAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;

    public CreatePermissionHandler(AccessWriter writer, IdentityAuditWriter audit, UnitOfWork transactions, Clock clock,
        Authorization authorization) {
        this.authorization = authorization;
        this.writer = writer;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
    }

    public UUID handle(CreatePermissionCommand command) {
        authorization.requirePermission(command.actorId(), "identity.manage");
        String value = IdentityRules.normalize(command.code());
        if (!value.matches("[a-z][a-z0-9_]*[.][a-z][a-z0-9_]*") || value.length() > 100) {
            throw new IdentityException(IdentityException.Kind.INVALID, "Invalid code.");
        }
        String description = command.description() == null ? "" : command.description().strip();
        if (description.length() > 255) {
            throw new IdentityException(IdentityException.Kind.INVALID, "Description must be at most 255 characters.");
        }
        return transactions.execute(() -> {
            UUID id = UUID.randomUUID();
            writer.createPermission(id, value, description);
            audit.audit(command.actorId(), "permission.created", id, clock.instant());
            return id;
        });
    }
}
