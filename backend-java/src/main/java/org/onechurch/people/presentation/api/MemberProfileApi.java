package org.onechurch.people.presentation.api;
import org.onechurch.people.application.query.getmyprofile.GetMyProfileHandler;
import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.Gender;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.UUID;
@Tag(name = "My member profile", description = "Own verified People identity only. Membership, birth dates, lifecycle and guardian authority remain staff-managed. Contact changes preserve history and do not change the account's login email or confer verified messaging consent.")
public interface MemberProfileApi {
    record ProfileBody(String titleCode, @NotBlank @Size(max = 100) String firstName,
        @NotBlank @Size(max = 100) String lastName, @NotNull Gender gender, @Min(0) long expectedVersion) {}
    record ContactBody(UUID contactId, @NotNull ContactMethod.Kind kind, @NotBlank @Size(max = 254) String value,
        @Min(0) long expectedVersion) {}
    record Created(UUID id) {}
    @GetMapping("/api/member/profile")
    @Operation(summary = "Read my profile and active contact details", description = "Unlinked or revoked member access returns profile:null. No household, child, safeguarding or financial records are included.")
    ResponseEntity<GetMyProfileHandler.Result> profile();
    @PutMapping("/api/member/profile")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Update my own name, title and gender")
    void update(@Valid @RequestBody ProfileBody body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PutMapping("/api/member/profile/contacts")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Add or replace one of my contact details", description = "A replacement ends the old contact and creates a new record. The new contact becomes primary for its kind. Contact ownership and the person version are checked.")
    Created saveContact(@Valid @RequestBody ContactBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @DeleteMapping("/api/member/profile/contacts/{contactId}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "End one of my contact details")
    void removeContact(@PathVariable UUID contactId, @RequestParam long expectedVersion,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
