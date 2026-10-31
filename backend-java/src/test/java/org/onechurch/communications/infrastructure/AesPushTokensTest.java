package org.onechurch.communications.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
class AesPushTokensTest {
    @Test
    void encryptsWithAuthenticatedRandomNoncesAndRejectsTampering() {
        AesPushTokens tokens = new AesPushTokens("MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=");
        String a = tokens.encrypt("private-token"), b = tokens.encrypt("private-token");
        assertThat(a).isNotEqualTo(b).doesNotContain("private-token");
        assertThat(tokens.decrypt(a)).isEqualTo("private-token");
        assertThatThrownBy(() -> tokens.decrypt(a + "changed")).isInstanceOf(RuntimeException.class);
    }
    @Test
    void absentKeyNeverFallsBackToPlaintext() {
        AesPushTokens tokens = new AesPushTokens("");
        assertThat(tokens.available()).isFalse();
        assertThatThrownBy(() -> tokens.encrypt("secret")).isInstanceOf(RuntimeException.class);
    }
}
