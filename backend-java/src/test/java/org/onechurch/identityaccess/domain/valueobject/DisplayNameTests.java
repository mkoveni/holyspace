package org.onechurch.identityaccess.domain.valueobject;

import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;

class DisplayNameTests {

    @Test
    void rejectsBlankDisplayNames() {
        assertThatThrownBy(() -> new DisplayName(" ")).isInstanceOf(IdentityException.class);
    }
}
