package org.onechurch.shared.infrastructure;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.onechurch.shared.infrastructure.jooq.Tables.LOGIN_ATTEMPTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLES;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.USER_ACCOUNTS;

import org.assertj.core.api.ThrowableAssert.ThrowingCallable;
import org.jooq.DSLContext;
import org.junit.jupiter.api.BeforeEach;
import org.onechurch.identityaccess.application.command.bootstrapadministrator.BootstrapAdministratorCommand;
import org.onechurch.identityaccess.application.command.bootstrapadministrator.BootstrapAdministratorHandler;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.identityaccess.domain.IdentityException;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.test.web.server.LocalServerPort;
import org.springframework.context.annotation.Import;
import org.springframework.security.core.context.SecurityContext;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.oauth2.core.OAuth2AccessToken;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.security.oauth2.server.resource.authentication.BearerTokenAuthentication;
import org.springframework.security.oauth2.server.resource.introspection.OpaqueTokenIntrospector;

import tools.jackson.databind.json.JsonMapper;

import java.time.Instant;
import java.util.Map;
import java.util.UUID;
import java.util.function.Supplier;

@Import(TestcontainersConfiguration.class)
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
@SuppressWarnings("unchecked")
public abstract class IdentityIntegrationTestSupport {

    protected static final String PASSWORD = "A-strong-password-2026";

    protected static final UUID ADMIN_ROLE = UUID.fromString("00000000-0000-0000-0000-000000000001");

    protected static final UUID MANAGE_PERMISSION = UUID.fromString("00000000-0000-0000-0000-000000000002");

    private final JsonMapper json = JsonMapper.builder().build();

    @Autowired protected DSLContext db;

    @Autowired protected BootstrapAdministratorHandler bootstrap;

    @Autowired protected TokenCodec tokens;

    @Autowired private OpaqueTokenIntrospector introspector;

    @LocalServerPort protected int port;

    protected UUID adminId;
    protected String admin;

    @BeforeEach
    protected void resetIdentity() throws Exception {
        db.truncate(USER_ACCOUNTS).cascade().execute();
        db.truncate(ROLES).cascade().execute();
        db.truncate(PERMISSIONS).cascade().execute();
        db.truncate(LOGIN_ATTEMPTS).execute();
        db.insertInto(ROLES)
            .set(ROLES.ID, ADMIN_ROLE)
            .set(ROLES.NAME, "administrator")
            .set(ROLES.DESCRIPTION, "Account administrators")
            .execute();
        db.insertInto(PERMISSIONS)
            .set(PERMISSIONS.ID, MANAGE_PERMISSION)
            .set(PERMISSIONS.CODE, "identity.manage")
            .set(PERMISSIONS.DESCRIPTION, "Manage identity")
            .execute();
        db.insertInto(ROLE_PERMISSIONS)
            .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
            .set(ROLE_PERMISSIONS.PERMISSION_ID, MANAGE_PERMISSION)
            .execute();
        adminId = bootstrap
            .handle(new BootstrapAdministratorCommand("admin", "Admin@Church.org", "Church Administrator", PASSWORD));
        admin = login("admin", PASSWORD);
    }

    protected <T> T asActor(String token, Supplier<T> operation) {
        OAuth2AuthenticatedPrincipal principal = introspector.introspect(token);
        Instant now = Instant.now();
        OAuth2AccessToken credentials =
            new OAuth2AccessToken(OAuth2AccessToken.TokenType.BEARER, token, now, now.plusSeconds(3600));
        SecurityContext previous = SecurityContextHolder.getContext();
        SecurityContext context = SecurityContextHolder.createEmptyContext();
        context.setAuthentication(new BearerTokenAuthentication(principal, credentials, principal.getAuthorities()));
        try {
            SecurityContextHolder.setContext(context);
            return operation.get();
        } finally {
            SecurityContextHolder.setContext(previous);
        }
    }

    protected void assertIdentityFailure(ThrowingCallable operation, IdentityException.Kind expected) {
        assertThatThrownBy(operation).isInstanceOfSatisfying(IdentityException.class,
            error -> assertThat(error.kind()).isEqualTo(expected));
    }

    protected UUID createUser(String username, String email, String name) throws Exception {
        return created(request("POST", "/api/users", admin,
            Map.of("username", username, "email", email, "displayName", name, "password", PASSWORD)));
    }

    protected UUID created(Response response) {
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(201);
        return UUID.fromString((String) response.data().get("id"));
    }

    protected String login(String username, String password) throws Exception {
        var response = request("POST", "/api/login_check", null, Map.of("username", username, "password", password));
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(200);
        return (String) response.data().get("token");
    }

    @SuppressWarnings("unchecked")
    protected Response request(String method, String path, String token, Object body) throws Exception {
        io.restassured.specification.RequestSpecification spec =
            io.restassured.RestAssured.given().baseUri("http://localhost").port(port).contentType("application/json");
        if (token != null) {
            spec.header("Authorization", "Bearer " + token);
        }
        if (body != null) {
            spec.body(json.writeValueAsString(body));
        }
        io.restassured.response.Response response = spec.request(method, path);
        String content = response.asString();
        Object decoded = content.isEmpty() ? Map.of() : json.readValue(content, Object.class);
        return new Response(response.statusCode(), content,
            decoded instanceof Map ? (Map<String, Object>) decoded : Map.of());
    }

    protected record Response(int status, String body, Map<String, Object> data) {}
}
