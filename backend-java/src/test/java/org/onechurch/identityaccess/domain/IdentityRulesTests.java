package org.onechurch.identityaccess.domain;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.util.Set;

class IdentityRulesTests {

    @Test
    void expandsManagementGrantsWithoutChangingInputOrAllowingResultMutation() {
        Set<String> granted = Set.of("identity.manage", "volunteering.manage", "ministries.manage", "followups.manage",
            "custom.export", "identity.read");

        Set<String> effective = IdentityRules.effectivePermissions(granted);

        assertThat(effective).containsExactlyInAnyOrder("identity.manage", "identity.read", "volunteering.manage",
            "volunteering.read", "volunteering.serve", "ministries.manage", "ministries.read", "ministries.lead",
            "followups.manage", "followups.read", "followups.work", "custom.export");
        assertThat(granted).hasSize(6).doesNotContain("volunteering.serve", "ministries.lead");
        assertThatThrownBy(() -> effective.add("identity.delete")).isInstanceOf(UnsupportedOperationException.class);
    }

    @Test
    void emptyAndReadOnlyGrantsDoNotGainManagementPermissions() {
        assertThat(IdentityRules.effectivePermissions(Set.of())).isEmpty();
        assertThat(IdentityRules.effectivePermissions(Set.of("identity.read", "custom.export")))
            .containsExactlyInAnyOrder("identity.read", "custom.export");
    }
}
