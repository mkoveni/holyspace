package org.onechurch.shared.infrastructure;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_HOUSEHOLDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_PERSONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_TITLES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLES;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ROLES;

import org.junit.jupiter.api.BeforeEach;

import java.util.Map;
import java.util.UUID;
public abstract class PeopleIntegrationTestSupport extends IdentityIntegrationTestSupport {
    protected static final UUID PEOPLE_MANAGE = UUID.fromString("00000000-0000-0000-0000-000000000004");
    protected static final UUID PEOPLE_READ = UUID.fromString("00000000-0000-0000-0000-000000000003");

    @BeforeEach
    protected void resetPeople() {
        db.truncate(PEOPLE_HOUSEHOLDS).cascade().execute();
        db.truncate(PEOPLE_PERSONS).cascade().execute();
        db.truncate(PEOPLE_AUDIT).execute();
        db.deleteFrom(PEOPLE_TITLES).where(PEOPLE_TITLES.CODE.eq("PASTOR")).execute();
        db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, PEOPLE_MANAGE).set(PERMISSIONS.CODE, "people.manage").execute();
        db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, PEOPLE_READ).set(PERMISSIONS.CODE, "people.read").execute();
        db.insertInto(ROLE_PERMISSIONS)
            .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
            .set(ROLE_PERMISSIONS.PERMISSION_ID, PEOPLE_MANAGE)
            .execute();
    }
    protected UUID person(String name) throws Exception {
        return created(
            request("POST", "/api/people", admin, Map.of("person", Map.of("firstName", name, "lastName", "Mabasa"))));
    }
    protected UUID household(String name) throws Exception {
        return created(request("POST", "/api/households", admin, Map.of("name", name)));
    }
    protected UUID member(UUID household, UUID person, long version, boolean primary) throws Exception {
        return created(request("POST", "/api/households/" + household + "/members", admin,
            Map.of("personId", person, "expectedVersion", version, "primary", primary)));
    }
    protected UUID relationship(UUID source, UUID target, String type) throws Exception {
        return created(
            request("POST", "/api/relationships", admin, Map.of("sourceId", source, "targetId", target, "type", type)));
    }
    protected long personVersion(UUID person) throws Exception {
        Response response = request("GET", "/api/people/" + person, admin, null);
        assertThat(response.status()).isEqualTo(200);
        return ((Number) response.data().get("version")).longValue();
    }
    protected String readerToken() throws Exception {
        UUID user = createUser("reader", "reader@church.org", "People Reader");
        UUID role = UUID.randomUUID();
        db.insertInto(ROLES).set(ROLES.ID, role).set(ROLES.NAME, "people-reader").execute();
        db.insertInto(USER_ROLES).set(USER_ROLES.USER_ID, user).set(USER_ROLES.ROLE_ID, role).execute();
        db.insertInto(ROLE_PERMISSIONS)
            .set(ROLE_PERMISSIONS.ROLE_ID, role)
            .set(ROLE_PERMISSIONS.PERMISSION_ID, PEOPLE_READ)
            .execute();
        return login("reader", PASSWORD);
    }
}
