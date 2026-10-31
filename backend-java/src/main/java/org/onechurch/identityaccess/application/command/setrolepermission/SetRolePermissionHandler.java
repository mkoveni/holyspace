package org.onechurch.identityaccess.application.command.setrolepermission;

import org.onechurch.identityaccess.application.authorization.Authorization;
import org.onechurch.identityaccess.application.contract.AccessReader;
import org.onechurch.identityaccess.application.contract.AccessWriter;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.domain.AccountManagementPolicy;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;

public final class SetRolePermissionHandler {

    private final Authorization authorization;

    private final AccountReader accounts;
    private final AccessReader access;
    private final AccessWriter writer;
    private final IdentityAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;

    public SetRolePermissionHandler(AccountReader accounts, AccessReader access, AccessWriter writer,
        IdentityAuditWriter audit, UnitOfWork transactions, Clock clock, Authorization authorization) {
        this.authorization = authorization;
        this.accounts = accounts;
        this.access = access;
        this.writer = writer;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
    }

    public void handle(SetRolePermissionCommand command) {
        transactions.execute(() -> {
            writer.lockAccessChanges();
            authorization.requirePermission(command.actorId(), "identity.manage");
            if (access.roles().stream().noneMatch(r -> r.id().equals(command.roleId()))
                || access.permissions().stream().noneMatch(p -> p.id().equals(command.permissionId()))) {
                throw new IdentityException(IdentityException.Kind.NOT_FOUND, "Role or permission not found.");
            }
            writer.assignPermission(command.roleId(), command.permissionId(), command.assign());
            AccountManagementPolicy.requireActiveManager(access.activeManagers());
            audit.audit(command.actorId(), "rolepermission." + (command.assign() ? "assigned" : "removed"),
                command.roleId(), clock.instant());
            return null;
        });
    }
}
