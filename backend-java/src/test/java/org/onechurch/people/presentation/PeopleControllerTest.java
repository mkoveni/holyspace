package org.onechurch.people.presentation;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;

import java.util.Map;
import java.util.UUID;

class PeopleControllerTest extends PeopleIntegrationTestSupport {

    @Test
    void supportsExactlyFourMembershipTypesAndAnExtensibleOptionalTitleCatalogue() throws Exception {
        Response metadata = request("GET", "/api/people/metadata", admin, null);
        assertThat(metadata.status()).isEqualTo(200);
        assertThat(metadata.body()).contains("Active Member", "Regular Visitor", "Visitor", "Transferred", "Dr",
            "Miss");
        assertThat((java.util.List<?>) metadata.data().get("membershipTypes")).hasSize(4);
        assertThat(metadata.body()).doesNotContain("PROSPECT", "INACTIVE");
        assertThat(request("POST", "/api/people/titles", admin, Map.of("code", "pastor", "label", "Pastor")).status())
            .isEqualTo(204);
        UUID id = created(request("POST", "/api/people", admin,
            Map.of("person", Map.of("firstName", "Solly", "lastName", "Mabasa", "titleCode", "pastor"),
                "membershipType", "ACTIVE_MEMBER")));
        Response profile = request("GET", "/api/people/" + id, admin, null);
        assertThat(profile.body()).contains("PASTOR", "ACTIVE_MEMBER", "UNKNOWN");
        assertThat(request("POST", "/api/people", admin,
            Map.of("person", Map.of("firstName", "A", "lastName", "B"), "membershipType", "PROSPECT")).status())
            .isEqualTo(400);
        assertThat(request("POST", "/api/people", admin,
            Map.of("person", Map.of("firstName", "A", "lastName", "B", "titleCode", "MISSING"))).status())
            .isEqualTo(400);
    }

    @Test
    void validatesDatesAndDerivesAgeOnTheRequestedDate() throws Exception {
        UUID id = created(request("POST", "/api/people", admin,
            Map.of("person", Map.of("firstName", "Child", "lastName", "Mabasa", "dateOfBirth", "2008-10-26"))));
        assertThat(request("GET", "/api/people/" + id + "?asOf=2026-10-25", admin, null).data().get("ageCategory"))
            .isEqualTo("CHILD");
        assertThat(request("GET", "/api/people/" + id + "?asOf=2026-10-26", admin, null).data().get("ageCategory"))
            .isEqualTo("ADULT");
        assertThat(request("POST", "/api/people", admin,
            Map.of("person", Map.of("firstName", "A", "lastName", "B", "dateOfBirth", "2999-01-01"))).status())
            .isEqualTo(400);
    }

    @Test
    void sharesContactsAcrossPeopleAndKeepsAHistoryWithOnePrimaryPerKind() throws Exception {
        UUID a = person("Solly");
        UUID b = person("Child");
        UUID contact = created(request("POST", "/api/people/" + a + "/contacts", admin,
            Map.of("expectedVersion", 0, "kind", "EMAIL", "value", "Family@Church.org", "primary", true)));
        created(request("POST", "/api/people/" + b + "/contacts", admin,
            Map.of("expectedVersion", 0, "kind", "EMAIL", "value", "family@church.org", "primary", true)));
        created(request("POST", "/api/people/" + a + "/contacts", admin,
            Map.of("expectedVersion", 1, "kind", "EMAIL", "value", "other@church.org", "primary", true)));
        assertThat(request("POST", "/api/people/" + a + "/contacts", admin,
            Map.of("expectedVersion", 2, "kind", "EMAIL", "value", "family@church.org", "primary", true)).status())
            .isEqualTo(409);
        assertThat(personVersion(a)).isEqualTo(2);
        assertThat(
            request("POST", "/api/people/" + a + "/contacts/" + contact + "/end", admin, Map.of("expectedVersion", 2))
                .status())
            .isEqualTo(204);
        Response profile = request("GET", "/api/people/" + a, admin, null);
        assertThat(profile.body()).contains("family@church.org", "endedAt", "other@church.org");
        assertThat(request("POST", "/api/people/" + a + "/contacts", admin,
            Map.of("expectedVersion", 3, "kind", "EMAIL", "value", "bad address")).status()).isEqualTo(400);
        assertThat(personVersion(a)).isEqualTo(3);
    }

    @Test
    void changesMembershipAndArchivesWithoutLosingIdentityOrHistory() throws Exception {
        UUID id = person("Solly");
        Map<String, Object> profile = Map.of("expectedVersion", 0, "person",
            Map.of("firstName", "Solly", "lastName", "Mabasa", "titleCode", "DR"), "membershipType", "TRANSFERRED");
        assertThat(request("PUT", "/api/people/" + id, admin, profile).status()).isEqualTo(204);
        assertThat(request("PUT", "/api/people/" + id, admin, profile).status()).isEqualTo(409);
        assertThat(
            request("PUT", "/api/people/" + id + "/archive", admin, Map.of("expectedVersion", 1, "archived", true))
                .status())
            .isEqualTo(204);
        assertThat(request("GET", "/api/people?search=Solly", admin, null).body()).doesNotContain(id.toString());
        assertThat(request("GET", "/api/people?search=Solly&includeArchived=true", admin, null).body())
            .contains(id.toString());
        assertThat(
            request("PUT", "/api/people/" + id + "/archive", admin, Map.of("expectedVersion", 2, "archived", false))
                .status())
            .isEqualTo(204);
        assertThat(request("GET", "/api/people/" + id, admin, null).body()).contains("TRANSFERRED", "DR");
    }

    @Test
    void addsAndEndsAddressesWithPersonRevisionAndHistory() throws Exception {
        UUID id = person("Solly");
        UUID address = created(request("POST", "/api/people/" + id + "/addresses", admin,
            Map.of("expectedVersion", 0, "line1", "1 Church Road", "city", "Johannesburg", "countryCode", "za")));
        assertThat(
            request("POST", "/api/people/" + id + "/addresses/" + address + "/end", admin, Map.of("expectedVersion", 1))
                .status())
            .isEqualTo(204);
        assertThat(request("GET", "/api/people/" + id, admin, null).body()).contains("1 Church Road", "ZA");
        assertThat(personVersion(id)).isEqualTo(2);
    }

    @Test
    void enforcesReadAndManageAtTheHttpBoundary() throws Exception {
        assertThat(request("GET", "/api/people/metadata", null, null).status()).isEqualTo(401);
        String reader = readerToken();
        assertThat(request("GET", "/api/people/metadata", reader, null).status()).isEqualTo(200);
        assertThat(request("POST", "/api/people", reader, Map.of("person", Map.of("firstName", "A", "lastName", "B")))
            .status()).isEqualTo(403);
    }
}
