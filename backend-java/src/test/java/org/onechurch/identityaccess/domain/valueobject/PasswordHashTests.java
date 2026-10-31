package org.onechurch.identityaccess.domain.valueobject;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;

class PasswordHashTests {

    @Test
    void rejectsBlankHashesAndRedactsDiagnosticOutput() {
        assertThatThrownBy(() -> new PasswordHash("")).isInstanceOf(IdentityException.class);
        assertThat(new PasswordHash("new-hash").toString()).doesNotContain("new-hash");
    }
}
