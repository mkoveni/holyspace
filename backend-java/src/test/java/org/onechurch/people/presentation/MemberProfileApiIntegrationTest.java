package org.onechurch.people.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.IDENTITY_MEMBER_LINK_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_CONTACT_METHODS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_PERSONS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.PaymentIntegrationTestSupport;

import java.time.LocalDate;
import java.util.Map;
import java.util.UUID;
class MemberProfileApiIntegrationTest extends PaymentIntegrationTestSupport {
    private UUID member, person;
    private String token;
    @BeforeEach
    void setupMember() throws Exception {
        member = createUser("member", "member@example.org", "Member");
        token = login("member", PASSWORD);
        person = person("Solly");
    }
    private void link(boolean active, long version) throws Exception {
        Response result = request("PUT", "/api/member-access/" + member, admin, Map.of("personId", person, "active",
            active, "expectedVersion", version, "evidence", "Account holder verified in person"));
        assertThat(result.status()).withFailMessage(result.body()).isEqualTo(204);
    }
    @Test
    void memberIdentityIsExplicitAndDoesNotGrantFinanceOrDirectoryAccess() throws Exception {
        assertThat(request("GET", "/api/member/profile", null, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/member/profile", token, null).data().get("profile")).isNull();
        link(true, 0);
        assertThat(request("GET", "/api/member/profile", token, null).body()).contains(person.toString(), "Solly");
        assertThat(request("GET", "/api/people", token, null).status()).isEqualTo(403);
        assertThat(request("GET", "/api/my-giving", token, null).data().get("donor")).isNull();
        assertThat(request("PUT", "/api/member-access/" + member, token,
            Map.of("personId", person, "active", true, "expectedVersion", 1, "evidence", "Self-claim")).status())
            .isEqualTo(403);
    }
    @Test
    void selfEditsPreserveStaffManagedMembershipBirthDateAndLifecycle() throws Exception {
        db.update(PEOPLE_PERSONS)
            .set(PEOPLE_PERSONS.DATE_OF_BIRTH, LocalDate.of(1990, 2, 10))
            .where(PEOPLE_PERSONS.ID.eq(person))
            .execute();
        link(true, 0);
        Response updated = request("PUT", "/api/member/profile", token,
            Map.of("titleCode", "MR", "firstName", "Solly Updated", "lastName", "Mabasa", "gender", "MALE",
                "expectedVersion", 0, "dateOfBirth", "2018-01-01", "membershipType", "TRANSFERRED", "personId",
                UUID.randomUUID()));
        assertThat(updated.status()).withFailMessage(updated.body()).isEqualTo(204);
        assertThat(db.select(PEOPLE_PERSONS.DATE_OF_BIRTH)
            .from(PEOPLE_PERSONS)
            .where(PEOPLE_PERSONS.ID.eq(person))
            .fetchOne(PEOPLE_PERSONS.DATE_OF_BIRTH)).isEqualTo(LocalDate.of(1990, 2, 10));
        assertThat(request("GET", "/api/member/profile", token, null).body()).contains("Solly Updated", "VISITOR");
        assertThat(request("PUT", "/api/member/profile", token,
            Map.of("firstName", "Stale", "lastName", "Name", "gender", "UNKNOWN", "expectedVersion", 0)).status())
            .isEqualTo(409);
    }
    @Test
    void contactsAreOwnedVersionedAndReplacedWithoutErasingHistory() throws Exception {
        link(true, 0);
        UUID first = created(request("PUT", "/api/member/profile/contacts", token,
            Map.of("kind", "EMAIL", "value", "first@example.org", "expectedVersion", 0)));
        UUID second = created(request("PUT", "/api/member/profile/contacts", token,
            Map.of("contactId", first, "kind", "EMAIL", "value", "second@example.org", "expectedVersion", 1)));
        assertThat(db.fetchCount(PEOPLE_CONTACT_METHODS, PEOPLE_CONTACT_METHODS.PERSON_ID.eq(person))).isEqualTo(2);
        assertThat(db.select(PEOPLE_CONTACT_METHODS.ENDED_AT)
            .from(PEOPLE_CONTACT_METHODS)
            .where(PEOPLE_CONTACT_METHODS.ID.eq(first))
            .fetchOne(PEOPLE_CONTACT_METHODS.ENDED_AT)).isNotNull();
        assertThat(request("GET", "/api/me", token, null).body()).contains("member@example.org")
            .doesNotContain("second@example.org");
        UUID other = person("Another"), otherContact = created(request("POST", "/api/people/" + other + "/contacts",
            admin, Map.of("kind", "EMAIL", "value", "other@example.org", "expectedVersion", 0, "primary", true)));
        assertThat(request("DELETE", "/api/member/profile/contacts/" + otherContact + "?expectedVersion=2", token, null)
            .status()).isEqualTo(404);
        assertThat(request("PUT", "/api/member/profile/contacts", token,
            Map.of("contactId", otherContact, "kind", "EMAIL", "value", "steal@example.org", "expectedVersion", 2))
            .status()).isEqualTo(404);
        assertThat(
            request("DELETE", "/api/member/profile/contacts/" + second + "?expectedVersion=2", token, null).status())
            .isEqualTo(204);
    }
    @Test
    void revocationAndCanonicalFinancialIdentityCannotBeBypassed() throws Exception {
        link(true, 0);
        UUID other = person("Different");
        assertThat(request("PUT", "/api/giving/donor-access/" + member, admin,
            Map.of("personId", other, "active", true, "expectedVersion", 0, "evidence", "Incorrect financial mapping"))
            .status()).isEqualTo(409);
        link(false, 1);
        assertThat(request("GET", "/api/member/profile", token, null).data().get("profile")).isNull();
        assertThat(request("PUT", "/api/member/profile", token,
            Map.of("firstName", "Wrong", "lastName", "Person", "gender", "UNKNOWN", "expectedVersion", 0)).status())
            .isEqualTo(403);
        assertThat(request("PUT", "/api/member-access/" + member, admin,
            Map.of("personId", other, "active", true, "expectedVersion", 2, "evidence", "Reassignment")).status())
            .isEqualTo(409);
        assertThat(db.fetchCount(IDENTITY_MEMBER_LINK_HISTORY)).isEqualTo(2);
        link(true, 2);
    }
    @Test
    void donorIdentityMustAgreeBeforeMemberAccessCanBeGranted() throws Exception {
        UUID donor = person("Existing donor");
        assertThat(request("PUT", "/api/giving/donor-access/" + member, admin,
            Map.of("personId", donor, "active", true, "expectedVersion", 0, "evidence", "Financial identity verified"))
            .status()).isEqualTo(204);
        assertThat(request("PUT", "/api/member-access/" + member, admin,
            Map.of("personId", person, "active", true, "expectedVersion", 0, "evidence", "Wrong member identity"))
            .status()).isEqualTo(409);
        revoke("people.manage");
        assertThat(request("GET", "/api/member-access", admin, null).status()).isEqualTo(403);
    }
}
