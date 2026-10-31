package org.onechurch.giving.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.util.UUID;
class DonorLinkTest {
    @Test
    void revocationPreservesIdentityAndRequiresCurrentVersion() {
        DonorLink link = new DonorLink(UUID.randomUUID(), UUID.randomUUID(), true, 1, UUID.randomUUID(), Instant.now());
        DonorLink revoked = link.revise(link.personId(), false, 1, link.verifiedBy(), Instant.now());
        assertThat(revoked.active()).isFalse();
        assertThat(revoked.version()).isEqualTo(2);
        assertThat(revoked.accountId()).isEqualTo(link.accountId());
        assertThatThrownBy(() -> revoked.revise(revoked.personId(), true, 1, link.verifiedBy(), Instant.now()))
            .isInstanceOf(GivingException.class);
        assertThatThrownBy(() -> revoked.revise(UUID.randomUUID(), true, 2, link.verifiedBy(), Instant.now()))
            .isInstanceOf(GivingException.class);
    }
}
