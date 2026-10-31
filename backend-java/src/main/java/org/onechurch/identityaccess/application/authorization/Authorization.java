package org.onechurch.identityaccess.application.authorization;
import org.onechurch.identityaccess.application.contract.AccessReader;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.application.contract.AuthenticatedAccount;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.CurrentActor.Actor;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.IdentityRules;

import java.time.Clock;
import java.util.UUID;

public final class Authorization implements PermissionChecker, AuthenticatedAccount {

    private final CurrentActor currentActor;
    private final AccountReader accounts;
    private final AccessReader access;
    private final SessionReader sessions;
    private final Clock clock;

    public Authorization(CurrentActor currentActor, AccountReader accounts, AccessReader access, SessionReader sessions,
        Clock clock) {
        this.currentActor = currentActor;
        this.accounts = accounts;
        this.access = access;
        this.sessions = sessions;
        this.clock = clock;
    }

    @Override
    public boolean hasPermission(String permission) {
        return IdentityRules.effectivePermissions(access.permissionCodes(authenticatedActor().accountId()))
            .contains(permission);
    }

    @Override
    public void requirePermission(String permission) {
        requirePermission(authenticatedActor(), permission);
    }

    @Override
    public void requirePermission(UUID claimedActorId, String permission) {
        Actor actor = authenticatedActor();
        requireMatchingAccount(actor, claimedActorId);
        requirePermission(actor, permission);
    }

    @Override
    public UUID requireAccount() {
        return authenticatedActor().accountId();
    }

    public void requireSelf(UUID accountId) {
        requireMatchingAccount(authenticatedActor(), accountId);
    }

    public void requireSelfOrPermission(UUID accountId, String permission) {
        Actor actor = authenticatedActor();
        if (!actor.accountId().equals(accountId)) {
            requirePermission(actor, permission);
        }
    }

    public void requireSession(UUID accountId, UUID sessionId) {
        Actor actor = authenticatedActor();
        requireMatchingAccount(actor, accountId);
        if (!actor.sessionId().equals(sessionId)) {
            throw forbidden();
        }
    }

    private Actor authenticatedActor() {
        Actor actor = currentActor.current().orElseThrow(Authorization::unauthenticated);
        sessions.findById(actor.sessionId())
            .filter(session -> session.accountId().equals(actor.accountId()))
            .filter(session -> session.expiresAt().isAfter(clock.instant()))
            .orElseThrow(Authorization::unauthenticated);
        accounts.find(actor.accountId()).filter(Account::active).orElseThrow(Authorization::unauthenticated);
        return actor;
    }

    private void requirePermission(Actor actor, String permission) {
        if (!IdentityRules.effectivePermissions(access.permissionCodes(actor.accountId())).contains(permission)) {
            throw forbidden();
        }
    }

    private void requireMatchingAccount(Actor actor, UUID accountId) {
        if (!actor.accountId().equals(accountId)) {
            throw forbidden();
        }
    }

    private static IdentityException unauthenticated() {
        return new IdentityException(IdentityException.Kind.UNAUTHENTICATED,
            "A valid authenticated session is required.");
    }

    private static IdentityException forbidden() {
        return new IdentityException(IdentityException.Kind.FORBIDDEN, "Permission denied.");
    }
}
