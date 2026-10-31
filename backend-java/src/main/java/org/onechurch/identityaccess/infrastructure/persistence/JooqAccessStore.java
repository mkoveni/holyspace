package org.onechurch.identityaccess.infrastructure.persistence;

import static org.jooq.impl.DSL.countDistinct;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLES;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ACCOUNTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ROLES;

import org.jooq.DSLContext;
import org.onechurch.identityaccess.application.contract.AccessReader;
import org.onechurch.identityaccess.application.contract.AccessWriter;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Set;
import java.util.UUID;

@Repository
public class JooqAccessStore implements AccessReader, AccessWriter {

    private final DSLContext db;

    public JooqAccessStore(DSLContext db) {
        this.db = db;
    }

    @Override
    public List<Role> roles() {
        return db.selectFrom(ROLES)
            .orderBy(ROLES.NAME)
            .fetch(r -> new Role(r.getId(), r.getName(), r.getDescription()));
    }

    @Override
    public List<Permission> permissions() {
        return db.selectFrom(PERMISSIONS)
            .orderBy(PERMISSIONS.CODE)
            .fetch(r -> new Permission(r.getId(), r.getCode(), r.getDescription()));
    }

    @Override
    public Set<String> roleNames(UUID id) {
        return Set.copyOf(db.select(ROLES.NAME)
            .from(ROLES)
            .join(USER_ROLES)
            .on(USER_ROLES.ROLE_ID.eq(ROLES.ID))
            .where(USER_ROLES.USER_ID.eq(id))
            .fetchSet(ROLES.NAME));
    }

    @Override
    public Set<String> permissionCodes(UUID id) {
        return Set.copyOf(db.selectDistinct(PERMISSIONS.CODE)
            .from(PERMISSIONS)
            .join(ROLE_PERMISSIONS)
            .on(ROLE_PERMISSIONS.PERMISSION_ID.eq(PERMISSIONS.ID))
            .join(USER_ROLES)
            .on(USER_ROLES.ROLE_ID.eq(ROLE_PERMISSIONS.ROLE_ID))
            .where(USER_ROLES.USER_ID.eq(id))
            .fetchSet(PERMISSIONS.CODE));
    }

    @Override
    public int activeManagers() {
        return db.select(countDistinct(USER_ACCOUNTS.ID))
            .from(USER_ACCOUNTS)
            .join(USER_ROLES)
            .on(USER_ROLES.USER_ID.eq(USER_ACCOUNTS.ID))
            .join(ROLE_PERMISSIONS)
            .on(ROLE_PERMISSIONS.ROLE_ID.eq(USER_ROLES.ROLE_ID))
            .join(PERMISSIONS)
            .on(PERMISSIONS.ID.eq(ROLE_PERMISSIONS.PERMISSION_ID))
            .where(USER_ACCOUNTS.STATUS.eq("active").and(PERMISSIONS.CODE.eq("identity.manage")))
            .fetchSingle()
            .value1();
    }

    @Override
    public void lockAccessChanges() {
        PostgresTransactionLocks.lockAccessChanges(db);
    }

    @Override
    public void createRole(UUID id, String name, String description) {
        db.insertInto(ROLES).set(ROLES.ID, id).set(ROLES.NAME, name).set(ROLES.DESCRIPTION, description).execute();
    }

    @Override
    public void createPermission(UUID id, String code, String description) {
        db.insertInto(PERMISSIONS)
            .set(PERMISSIONS.ID, id)
            .set(PERMISSIONS.CODE, code)
            .set(PERMISSIONS.DESCRIPTION, description)
            .execute();
    }

    @Override
    public void assignRole(UUID accountId, UUID roleId, boolean assign) {
        if (assign) {
            db.insertInto(USER_ROLES)
                .set(USER_ROLES.USER_ID, accountId)
                .set(USER_ROLES.ROLE_ID, roleId)
                .onConflictDoNothing()
                .execute();
        } else {
            db.deleteFrom(USER_ROLES)
                .where(USER_ROLES.USER_ID.eq(accountId).and(USER_ROLES.ROLE_ID.eq(roleId)))
                .execute();
        }
    }

    @Override
    public void assignPermission(UUID roleId, UUID permissionId, boolean assign) {
        if (assign) {
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, roleId)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, permissionId)
                .onConflictDoNothing()
                .execute();
        } else {
            db.deleteFrom(ROLE_PERMISSIONS)
                .where(ROLE_PERMISSIONS.ROLE_ID.eq(roleId).and(ROLE_PERMISSIONS.PERMISSION_ID.eq(permissionId)))
                .execute();
        }
    }
}
