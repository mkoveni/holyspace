package org.onechurch.shared.infrastructure;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.onechurch.shared.infrastructure.jooq.Tables.IDENTITY_ACCESS_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.LOGIN_ATTEMPTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLES;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.application.contract.AccessWriter;
import org.onechurch.identityaccess.application.contract.IdentityAuditWriter;
import org.onechurch.identityaccess.application.contract.LoginAttemptWriter;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.SessionWriter;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.beans.factory.annotation.Autowired;

import java.time.Instant;
import java.util.UUID;

class SpringUnitOfWorkIntegrationTests extends IdentityIntegrationTestSupport {
    @Autowired private UnitOfWork transactions;
    @Autowired private AccountRepository accounts;
    @Autowired private AccessWriter access;
    @Autowired private IdentityAuditWriter audit;
    @Autowired private SessionWriter sessions;
    @Autowired private SessionReader sessionReader;
    @Autowired private LoginAttemptWriter attempts;

    @Test
    void aFailedOperationRollsBackChangesAcrossAllPersistenceImplementations() throws Exception {
        var original = accounts.find(adminId).orElseThrow();
        var roleId = UUID.randomUUID();
        var attemptKey = tokens.hash("rollback-test");
        int originalAuditCount = db.fetchCount(IDENTITY_ACCESS_AUDIT);
        var now = Instant.now();

        assertThatThrownBy(() -> transactions.execute(() -> {
            var lockedAccount = accounts.findForUpdate(adminId).orElseThrow();
            accounts.save(lockedAccount.updateProfile("rollback@example.org", "Changed", now));
            access.createRole(roleId, "rollback-role", "A transactional role");
            access.assignRole(adminId, roleId, true);
            audit.audit(adminId, "transaction.rollback_test", roleId, now);
            sessions.revokeAll(adminId);
            attempts.failed(attemptKey, now);
            throw new IllegalStateException("Abort the entire operation");
        })).isInstanceOf(IllegalStateException.class).hasMessage("Abort the entire operation");

        assertThat(accounts.find(adminId).orElseThrow().email()).isEqualTo(original.email());
        assertThat(db.fetchCount(ROLES, ROLES.ID.eq(roleId))).isZero();
        assertThat(db.fetchCount(IDENTITY_ACCESS_AUDIT)).isEqualTo(originalAuditCount);
        assertThat(sessionReader.find(tokens.hash(admin))).isPresent();
        assertThat(db.fetchCount(LOGIN_ATTEMPTS, LOGIN_ATTEMPTS.IDENTIFIER_HASH.eq(attemptKey))).isZero();
        assertThat(request("GET", "/api/me", admin, null).status()).isEqualTo(200);
    }
}
