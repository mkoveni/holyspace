package org.onechurch.shared.infrastructure.documentation;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import io.swagger.v3.oas.models.Components;
import io.swagger.v3.oas.models.OpenAPI;
import io.swagger.v3.oas.models.info.Info;
import io.swagger.v3.oas.models.media.Content;
import io.swagger.v3.oas.models.media.MediaType;
import io.swagger.v3.oas.models.media.ObjectSchema;
import io.swagger.v3.oas.models.media.Schema;
import io.swagger.v3.oas.models.media.StringSchema;
import io.swagger.v3.oas.models.responses.ApiResponse;
import io.swagger.v3.oas.models.security.SecurityRequirement;
import io.swagger.v3.oas.models.security.SecurityScheme;

import java.util.List;

@Configuration
public class OpenApiConfiguration {

    @Bean
    OpenAPI oneChurchOpenApi() {
        Components components = new Components().addSecuritySchemes("bearerAuth", new SecurityScheme()
            .type(SecurityScheme.Type.HTTP)
            .scheme("bearer")
            .description("Sign in using POST /api/login_check, then paste the returned token here. "
                + "This is an opaque session token. Logout, password changes and account disabling revoke sessions."))
            .addSchemas("ApiError",
                new ObjectSchema().addProperty("message", new StringSchema()).required(List.of("message")))
            .addResponses("BadRequest", errorResponse("Invalid request or domain validation failure."))
            .addResponses("Unauthorized", errorResponse("Missing, invalid, expired or revoked credentials."))
            .addResponses("Forbidden",
                errorResponse("The account lacks the required permission or the current password is incorrect."))
            .addResponses("NotFound", errorResponse("The requested record does not exist."))
            .addResponses("Conflict",
                errorResponse(
                    "Duplicate data, overlapping associations, stale revisions or a protected business invariant."))
            .addResponses("TooManyRequests",
                errorResponse("Too many login attempts. Retry after the Retry-After header's delay."));
        return new OpenAPI()
            .info(new Info().title("OneChurch API")
                .version("v1")
                .description("OneChurch identity, access and People API. Sign in with a username or email address. "
                    + "Use the returned token as Authorization: Bearer YOUR_TOKEN for protected operations. "
                    + "Account and access administration requires identity.manage. "
                    + "People reads require people.read and writes require people.manage. "
                    + "Sessions expire after eight hours by default."))
            .components(components)
            .addSecurityItem(new SecurityRequirement().addList("bearerAuth"));
    }

    private static ApiResponse errorResponse(String description) {
        return new ApiResponse().description(description)
            .content(new Content().addMediaType("application/json",
                new MediaType().schema(new Schema<>().$ref("#/components/schemas/ApiError"))));
    }
}
