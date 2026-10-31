package org.onechurch.people.application.query.searchpeople;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.people.application.PersonSummary;
import org.onechurch.people.domain.AgeCategory;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.time.LocalDate;
import java.util.Map;
import java.util.UUID;

class SearchPeopleHandlerTest extends PeopleIntegrationTestSupport {
    @Autowired private SearchPeopleHandler handler;
    @Test
    void filtersChildrenAdultsAndUnknownAgesInSqlConsistentlyWithProfileClassification() throws Exception {
        UUID child = registered("Child", "2018-01-01");
        UUID adult = registered("Adult", "1980-01-01");
        UUID leap = registered("Leap", "2008-02-29");
        UUID unknown = person("Unknown");
        asActor(admin, () -> {
            LocalDate asOf = LocalDate.of(2026, 2, 28);
            assertThat(handler.handle(query(AgeCategory.CHILD, asOf))).extracting(PersonSummary::id)
                .containsExactly(child);
            assertThat(handler.handle(query(AgeCategory.ADULT, asOf))).extracting(PersonSummary::id)
                .containsExactlyInAnyOrder(adult, leap);
            assertThat(handler.handle(query(AgeCategory.UNKNOWN, asOf))).extracting(PersonSummary::id)
                .containsExactly(unknown);
            assertThat(handler.handle(query(AgeCategory.CHILD, asOf.minusDays(1)))).extracting(PersonSummary::id)
                .containsExactlyInAnyOrder(child, leap);
            return null;
        });
        Response response = request("GET", "/api/people?ageCategory=CHILD&asOf=2026-02-28", admin, null);
        assertThat(response.status()).isEqualTo(200);
        assertThat(response.body()).contains(child.toString())
            .doesNotContain(adult.toString(), leap.toString(), unknown.toString());
    }
    private SearchPeopleQuery query(AgeCategory category, LocalDate asOf) {
        return new SearchPeopleQuery("", false, null, category, asOf, 100, 0);
    }
    private UUID registered(String name, String birth) throws Exception {
        return created(request("POST", "/api/people", admin,
            Map.of("person", Map.of("firstName", name, "lastName", "Mabasa", "dateOfBirth", birth))));
    }
    @Test
    void combinesNameAndMembershipFiltersWithStablePaging() throws Exception {
        UUID b = person("Beta");
        UUID a = person("Alpha");
        asActor(admin, () -> {
            assertThat(handler.handle(new SearchPeopleQuery("Mabasa", false, MembershipType.VISITOR, null, null, 1, 0)))
                .extracting(PersonSummary::id)
                .containsExactly(a);
            assertThat(handler.handle(new SearchPeopleQuery("Mabasa", false, MembershipType.VISITOR, null, null, 1, 1)))
                .extracting(PersonSummary::id)
                .containsExactly(b);
            assertThat(
                handler.handle(new SearchPeopleQuery("", false, MembershipType.ACTIVE_MEMBER, null, null, 100, 0)))
                .isEmpty();
            return null;
        });
    }
}
