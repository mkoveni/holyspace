package org.onechurch.identityaccess.application.query.authenticatesession;

import org.onechurch.identityaccess.application.AccountView;
import org.onechurch.identityaccess.application.contract.AccessReader;
import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.SessionReader.Session;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.IdentityRules;

import java.time.Clock;
import java.util.UUID;

public final class AuthenticateSessionHandler {

    public record Identity(UUID sessionId, AccountView account) {}

    private final SessionReader sessions;
    private final AccountReader accounts;
    private final AccessReader access;
    private final TokenCodec tokens;
    private final Clock clock;

    public AuthenticateSessionHandler(SessionReader sessions, AccountReader accounts, AccessReader access,
        TokenCodec tokens, Clock clock) {
        this.sessions = sessions;
        this.accounts = accounts;
        this.access = access;
        this.tokens = tokens;
        this.clock = clock;
    }

    public Identity handle(AuthenticateSessionQuery query) {
        if (query.token() == null || !query.token().matches("[A-Za-z0-9_-]{43}")) {
            throw unauthorized();
        }

        Session session = sessions.find(tokens.hash(query.token()))
            .filter(sessionValue -> sessionValue.expiresAt().isAfter(clock.instant()))
            .orElseThrow(AuthenticateSessionHandler::unauthorized);

        return accounts.find(session.accountId())
            .filter(Account::active)
            .map(account -> new Identity(session.id(),
                AccountView.from(account, access.roleNames(account.id()),
                    IdentityRules.effectivePermissions(access.permissionCodes(account.id())))))
            .orElseThrow(AuthenticateSessionHandler::unauthorized);
    }

    private static IdentityException unauthorized() {
        return new IdentityException(IdentityException.Kind.UNAUTHENTICATED, "Invalid or expired session.");
    }
}
