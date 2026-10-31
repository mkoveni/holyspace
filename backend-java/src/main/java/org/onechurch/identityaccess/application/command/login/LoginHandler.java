package org.onechurch.identityaccess.application.command.login;

import org.onechurch.identityaccess.application.contract.AccountReader;
import org.onechurch.identityaccess.application.contract.LoginAttemptWriter;
import org.onechurch.identityaccess.application.contract.PasswordHasher;
import org.onechurch.identityaccess.application.contract.SessionWriter;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.identityaccess.domain.Account;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.identityaccess.domain.IdentityRules;
import org.onechurch.identityaccess.domain.repository.AccountRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Duration;
import java.time.Instant;
import java.util.Optional;
import java.util.UUID;

public final class LoginHandler {

    public record Result(String token) {

        @Override
        public String toString() {
            return "Login.Result[redacted]";
        }
    }

    private record Outcome(Result result, boolean throttled) {}

    private final AccountReader accounts;
    private final AccountRepository repository;
    private final LoginAttemptWriter attempts;
    private final SessionWriter sessions;
    private final PasswordHasher passwords;
    private final TokenCodec tokens;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final Duration lifetime;
    private final String dummyHash;

    public LoginHandler(AccountReader accounts, AccountRepository repository, LoginAttemptWriter attempts,
        SessionWriter sessions, PasswordHasher passwords, TokenCodec tokens, UnitOfWork transactions, Clock clock,
        Duration lifetime) {
        this.accounts = accounts;
        this.repository = repository;
        this.attempts = attempts;
        this.sessions = sessions;
        this.passwords = passwords;
        this.tokens = tokens;
        this.transactions = transactions;
        this.clock = clock;
        this.lifetime = lifetime;
        this.dummyHash = passwords.hash(tokens.generate());
    }

    public Result handle(LoginCommand command) {
        String identifier = IdentityRules.normalize(command.username());
        String key = tokens.hash(identifier);
        Outcome outcome = transactions.execute(() -> authenticate(command, identifier, key));
        if (outcome.throttled()) {
            throw new IdentityException(IdentityException.Kind.THROTTLED,
                "Too many login attempts. Try again in one minute.");
        }
        if (outcome.result() == null) {
            throw new IdentityException(IdentityException.Kind.UNAUTHENTICATED, "Invalid username or password.");
        }
        return outcome.result();
    }

    private Outcome authenticate(LoginCommand command, String identifier, String key) {
        attempts.lockLogin(key);
        Instant now = clock.instant();
        if (attempts.throttled(key, now)) {
            return new Outcome(null, true);
        }
        Optional<Account> account =
            accounts.findByLogin(identifier).flatMap(found -> repository.findForUpdate(found.id()));
        boolean matched = passwords.matches(command.password(), account.map(Account::passwordHash).orElse(dummyHash));
        return account.filter(Account::active)
            .filter(ignored -> matched)
            .map(authenticated -> createSession(authenticated, key, now, command.deviceName()))
            .orElseGet(() -> recordFailedAttempt(key, now));
    }

    private Outcome createSession(Account account, String key, Instant now, String deviceName) {
        attempts.clearFailures(key);
        String token = tokens.generate();
        sessions.create(UUID.randomUUID(), account.id(), tokens.hash(token), now.plus(lifetime), now, deviceName);
        return new Outcome(new Result(token), false);
    }

    private Outcome recordFailedAttempt(String key, Instant now) {
        attempts.failed(key, now);
        return new Outcome(null, false);
    }
}
