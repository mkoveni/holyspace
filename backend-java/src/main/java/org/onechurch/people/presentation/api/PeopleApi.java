package org.onechurch.people.presentation.api;

import org.onechurch.people.application.PeopleMetadata;
import org.onechurch.people.application.PersonSummary;
import org.onechurch.people.application.PersonView;
import org.onechurch.people.application.RelationshipView;
import org.onechurch.people.domain.AgeCategory;
import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.Gender;
import org.onechurch.people.domain.HouseholdMembership;
import org.onechurch.people.domain.LifeStatus;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.PersonDetails;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.responses.ApiResponse;
import io.swagger.v3.oas.annotations.responses.ApiResponses;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Max;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;

@Tag(name = "People", description = "Read requires people.read; writes require people.manage. Titles and membership are independent of age, life status and households.")
@RequestMapping("/api/people")
@ApiResponses({@ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
    @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
    @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
    @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
    @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
public interface PeopleApi {
    @Schema(name = "PeopleCreatedRequest")
    record Created(UUID id) {}
    @Schema(name = "PeoplePersonDataRequest")
    record PersonData(@Size(max = 20) String titleCode, @NotBlank @Size(max = 100) String firstName,
        @NotBlank @Size(max = 100) String lastName, LocalDate dateOfBirth, Gender gender, LifeStatus lifeStatus,
        LocalDate dateOfDeath) {
        public PersonDetails details() {
            return new PersonDetails(titleCode, firstName, lastName, dateOfBirth, gender, lifeStatus, dateOfDeath);
        }
    }
    @Schema(name = "PeopleRegisterRequest")
    record Register(@NotNull @Valid PersonData person, MembershipType membershipType) {}
    @Schema(name = "PeopleProfileRequest")
    record Profile(@Min(0) long expectedVersion, @NotNull @Valid PersonData person,
        @NotNull MembershipType membershipType) {}
    @Schema(name = "PeopleArchiveRequest")
    record Archive(@Min(0) long expectedVersion, boolean archived) {}
    @Schema(name = "PeopleTitleRequest")
    record Title(@NotBlank @Size(max = 20) String code, @NotBlank @Size(max = 40) String label) {}
    @Schema(name = "PeopleContactRequest")
    record Contact(@Min(0) long expectedVersion, @NotNull ContactMethod.Kind kind,
        @NotBlank @Size(max = 254) String value, boolean primary) {}
    @Schema(name = "PeopleRevisionRequest")
    record Revision(@Min(0) long expectedVersion) {}
    @Schema(name = "PeopleAddressRequest")
    record Address(@Min(0) long expectedVersion, @NotBlank @Size(max = 200) String line1, @Size(max = 200) String line2,
        @NotBlank @Size(max = 100) String city, @Size(max = 100) String region, @Size(max = 20) String postalCode,
        @NotBlank @Size(min = 2, max = 2) String countryCode) {}

    @GetMapping
    @Operation(summary = "Search people", description = "Stable name/id ordering, maximum 100 results. Archived records are excluded by default.")
    List<PersonSummary> search(@RequestParam(defaultValue = "") @Size(max = 200) String search,
        @RequestParam(defaultValue = "false") boolean includeArchived,
        @RequestParam(required = false) MembershipType membershipType,
        @RequestParam(required = false) AgeCategory ageCategory, @RequestParam(required = false) LocalDate asOf,
        @RequestParam(defaultValue = "50") @Min(1) @Max(100) int limit,
        @RequestParam(defaultValue = "0") @Min(0) @Max(1000000) int offset);

    @GetMapping("/metadata")
    @Operation(summary = "List supported membership types, titles and classifications")
    PeopleMetadata metadata();

    @PostMapping("/titles")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Add an available person title", description = "Titles are a catalogue; they do not infer gender, family role or permissions.")
    void title(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @Valid @RequestBody Title request);

    @GetMapping("/{id}")
    @Operation(summary = "Get a person profile", description = "Includes contact/address history and revision. Age uses the supplied date or today's church-local date. Missing birth date gives UNKNOWN.")
    PersonView get(@PathVariable UUID id, @RequestParam(required = false) LocalDate asOf);

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Register a person", description = "Membership defaults to Visitor. Does not create a login account.")
    Created create(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @Valid @RequestBody Register request);

    @PutMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Update a person and membership type", description = "Supply the latest person version. Stale edits return 409.")
    void update(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody Profile request);

    @PutMapping("/{id}/archive")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Archive or restore a person", description = "Preserves identity and association history.")
    void archive(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody Archive request);

    @PostMapping("/{id}/contacts")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Add a contact method", description = "A new primary contact replaces the previous primary for that kind. Shared values across people are allowed. Increments person version.")
    Created contact(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody Contact request);

    @PostMapping("/{id}/contacts/{contactId}/end")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "End a contact method", description = "Preserves history and increments person version.")
    void endContact(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @PathVariable UUID contactId, @Valid @RequestBody Revision request);

    @PostMapping("/{id}/addresses")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Add a person address", description = "Does not infer a household or guardianship. Increments person version.")
    Created address(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody Address request);

    @PostMapping("/{id}/addresses/{addressId}/end")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "End a person address", description = "Preserves history and increments person version.")
    void endAddress(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @PathVariable UUID addressId, @Valid @RequestBody Revision request);

    @GetMapping("/{id}/households")
    @Operation(summary = "Get a person's household membership history", description = "Memberships are half-open periods; validTo null means current. A person may belong to multiple households.")
    List<HouseholdMembership> households(@PathVariable UUID id);

    @GetMapping("/{id}/relationships")
    @Operation(summary = "Get relationships from a person's perspective", description = "Reverse CHILD_OF and WARD_OF views are derived. A relationship never grants pickup authorization.")
    List<RelationshipView> relationships(@PathVariable UUID id);
}
