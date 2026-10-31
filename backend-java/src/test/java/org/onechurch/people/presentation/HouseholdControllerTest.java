package org.onechurch.people.presentation;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;

import java.util.Map;
import java.util.UUID;

class HouseholdControllerTest extends PeopleIntegrationTestSupport {

    @Test
    void supportsMultipleHouseholdsWithOnePrimaryAndContactMembershipIntegrity() throws Exception {
        UUID child = person("Child");
        UUID a = household("First Home");
        UUID b = household("Second Home");
        UUID first = member(a, child, 0, true);
        assertThat(request("POST", "/api/households/" + b + "/members", admin,
            Map.of("expectedVersion", 0, "personId", child, "primary", true)).status()).isEqualTo(409);
        UUID second = member(b, child, 0, false);
        assertThat(request("PUT", "/api/households/" + a + "/primary-contact", admin,
            Map.of("expectedVersion", 1, "membershipId", second)).status()).isEqualTo(404);
        assertThat(request("PUT", "/api/households/" + a + "/primary-contact", admin,
            Map.of("expectedVersion", 1, "membershipId", first)).status()).isEqualTo(204);
        assertThat(
            request("POST", "/api/households/" + a + "/members/" + first + "/end", admin, Map.of("expectedVersion", 2))
                .status())
            .isEqualTo(204);
        Response home = request("GET", "/api/households/" + a, admin, null);
        assertThat(home.body()).contains("\"primaryContactMembershipId\":null", first.toString(), "validTo");
        assertThat(request("PUT", "/api/households/" + a + "/primary-contact", admin,
            Map.of("expectedVersion", 3, "membershipId", first)).status()).isEqualTo(400);
        assertThat(request("GET", "/api/people/" + child + "/households", admin, null).body())
            .contains(first.toString(), second.toString());
        member(b, person("Adult"), 1, false);
    }

    @Test
    void rejectsDuplicateMembershipAndStaleHouseholdEditsAndAllowsRejoining() throws Exception {
        UUID p = person("Solly");
        UUID h = household("Home");
        UUID first = member(h, p, 0, false);
        assertThat(request("POST", "/api/households/" + h + "/members", admin,
            Map.of("expectedVersion", 1, "personId", p, "primary", false)).status()).isEqualTo(409);
        assertThat(
            request("PUT", "/api/households/" + h, admin, Map.of("expectedVersion", 0, "name", "Changed")).status())
            .isEqualTo(409);
        assertThat(
            request("POST", "/api/households/" + h + "/members/" + first + "/end", admin, Map.of("expectedVersion", 1))
                .status())
            .isEqualTo(204);
        UUID second = member(h, p, 2, false);
        assertThat(second).isNotEqualTo(first);
        assertThat(request("GET", "/api/households/" + h, admin, null).body()).contains(first.toString(),
            second.toString());
    }
}
