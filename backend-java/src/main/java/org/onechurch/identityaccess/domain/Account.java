package org.onechurch.identityaccess.domain;

import org.onechurch.identityaccess.domain.valueobject.AccountStatus;
import org.onechurch.identityaccess.domain.valueobject.DisplayName;
import org.onechurch.identityaccess.domain.valueobject.EmailAddress;
import org.onechurch.identityaccess.domain.valueobject.PasswordHash;
import org.onechurch.identityaccess.domain.valueobject.Username;

import java.time.Instant;
import java.util.Objects;
import java.util.UUID;

public final class Account {

    private final UUID id;
    private final Username username;
    private final EmailAddress email;
    private final DisplayName displayName;
    private final PasswordHash passwordHash;
    private final AccountStatus status;
    private final Instant createdAt;
    private final Instant updatedAt;

    private Account(UUID id, Username username, EmailAddress email, DisplayName displayName, PasswordHash passwordHash,
        AccountStatus status, Instant createdAt, Instant updatedAt) {
        this.id = Objects.requireNonNull(id);
        this.username = Objects.requireNonNull(username);
        this.email = Objects.requireNonNull(email);
        this.displayName = Objects.requireNonNull(displayName);
        this.passwordHash = Objects.requireNonNull(passwordHash);
        this.status = Objects.requireNonNull(status);
        this.createdAt = Objects.requireNonNull(createdAt);
        this.updatedAt = Objects.requireNonNull(updatedAt);
        if (updatedAt.isBefore(createdAt)) {
            throw new IdentityException(IdentityException.Kind.INVALID, "Account update cannot predate its creation.");
        }
    }

    public static Account register(UUID id, String username, String email, String displayName, String hash,
        Instant now) {
        return new Account(id, new Username(username), new EmailAddress(email), new DisplayName(displayName),
            new PasswordHash(hash), AccountStatus.ACTIVE, now, now);
    }

    public static Account reconstitute(UUID id, String username, String email, String displayName, String hash,
        String status, Instant createdAt, Instant updatedAt) {
        return new Account(id, new Username(username), new EmailAddress(email), new DisplayName(displayName),
            new PasswordHash(hash), AccountStatus.from(status), createdAt, updatedAt);
    }

    public Account updateProfile(String email, String displayName, Instant now) {
        return new Account(id, username, new EmailAddress(email), new DisplayName(displayName), passwordHash, status,
            createdAt, now);
    }

    public Account changePassword(String hash, Instant now) {
        return new Account(id, username, email, displayName, new PasswordHash(hash), status, createdAt, now);
    }

    public Account enable(Instant now) {
        return withStatus(AccountStatus.ACTIVE, now);
    }

    public Account disable(Instant now) {
        return withStatus(AccountStatus.DISABLED, now);
    }

    private Account withStatus(AccountStatus status, Instant now) {
        return new Account(id, username, email, displayName, passwordHash, status, createdAt, now);
    }

    public UUID id() {
        return id;
    }

    public String username() {
        return username.value();
    }

    public String email() {
        return email.value();
    }

    public String displayName() {
        return displayName.value();
    }

    public String passwordHash() {
        return passwordHash.value();
    }

    public String status() {
        return status.value();
    }

    public Instant createdAt() {
        return createdAt;
    }

    public Instant updatedAt() {
        return updatedAt;
    }

    public boolean active() {
        return status == AccountStatus.ACTIVE;
    }

    @Override
    public boolean equals(Object other) {
        return other instanceof Account account && id.equals(account.id);
    }

    @Override
    public int hashCode() {
        return id.hashCode();
    }
}
