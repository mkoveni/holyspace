package org.onechurch.identityaccess.domain;

import static org.assertj.core.api.Assertions.assertThatCode;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

class AccountManagementPolicyTests {

    @Test
    void managementPolicyPreventsRemovingTheLastManager() {
        assertThatCode(() -> AccountManagementPolicy.requireActiveManager(1)).doesNotThrowAnyException();
        assertThatThrownBy(() -> AccountManagementPolicy.requireActiveManager(0)).isInstanceOf(IdentityException.class);
    }
}
