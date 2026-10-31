package org.onechurch.identityaccess.domain;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.util.UUID;

class AccountTests {

    private final UUID id = UUID.randomUUID();
    private final Instant now = Instant.parse("2026-10-24T14:00:00Z");

    @Test
    void registrationOwnsNormalisationAndRequiredIdentity() {
        var account = Account.register(id, "SOLLY", "Solly@Church.org", "  Solly Mabasa  ", "stored-hash", now);
        assertThat(account.username()).isEqualTo("solly");
        assertThat(account.email()).isEqualTo("solly@church.org");
        assertThat(account.displayName()).isEqualTo("Solly Mabasa");
        assertThat(account.active()).isTrue();
        assertThatThrownBy(() -> Account.register(id, "solly", "invalid", "Solly", "stored-hash", now))
            .isInstanceOf(IdentityException.class);
        assertThatThrownBy(
            () -> Account.reconstitute(id, "solly", "solly@church.org", "Solly", "stored-hash", "unknown", now, now))
            .isInstanceOf(IdentityException.class);
    }

    @Test
    void profileAndLifecycleChangesPreserveAggregateIdentityAndCreationTime() {
        var original = Account.register(id, "solly", "solly@church.org", "Solly", "old-hash", now);
        var changed = original.updateProfile("New@Church.org", "Solly Mabasa", now.plusSeconds(1))
            .changePassword("new-hash", now.plusSeconds(2))
            .disable(now.plusSeconds(3));
        assertThat(changed).isEqualTo(original);
        assertThat(changed.hashCode()).isEqualTo(original.hashCode());
        assertThat(changed.username()).isEqualTo(original.username());
        assertThat(changed.createdAt()).isEqualTo(now);
        assertThat(changed.email()).isEqualTo("new@church.org");
        assertThat(changed.passwordHash()).isEqualTo("new-hash");
        assertThat(changed.active()).isFalse();
        assertThat(changed.enable(now.plusSeconds(4)).active()).isTrue();
        assertThat(original.active()).isTrue();
        assertThat(original.passwordHash()).isEqualTo("old-hash");
        assertThat(changed.toString()).doesNotContain("new-hash", "new@church.org");
    }

    @Test
    void aggregatesCannotPredateTheirCreationAndNeedDistinctIdentity() {
        var original = Account.register(id, "solly", "solly@church.org", "Solly", "hash", now);
        assertThatThrownBy(() -> original.disable(now.minusSeconds(1))).isInstanceOf(IdentityException.class);
        assertThat(original)
            .isNotEqualTo(Account.register(UUID.randomUUID(), "solly", "solly@church.org", "Solly", "hash", now));
    }
}
