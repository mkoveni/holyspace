package org.onechurch.childrenministry.participation.presentation.api;
import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.safeguarding.domain.ConsentPolicy;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Max;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
@Tag(name = "Children Participation")
@RequestMapping("/api/children")
public interface ParticipationApi {
    @Schema(name = "ChildrenParticipationCreated")
    record Created(UUID id) {}
    @Schema(name = "ChildrenCreateProgrammeRequest")
    record CreateProgrammeRequest(@Size(max = 2000) String name, @Min(1) @Max(10000) int minStaff,
        @Min(1) @Max(10000) int childrenPerStaff) {}
    @PostMapping("/programmes")
    @Operation(summary = "Create Programme", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created createProgramme(@Valid @RequestBody CreateProgrammeRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenPublishConsentPolicyRequest")
    record PublishConsentPolicyRequest(@NotNull UUID programmeId, @Min(0) long expectedVersion,
        @Size(max = 2000) String label, @Size(max = 2000) String documentReference,
        @Size(max = 2000) String contentHash) {}
    @PostMapping("/consent-policies")
    @Operation(summary = "Publish Consent Policy", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created publishConsentPolicy(@Valid @RequestBody PublishConsentPolicyRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenCreateClassRequest")
    record CreateClassRequest(@NotNull UUID programmeId, @Size(max = 2000) String name,
        @Min(0) @Max(215) int minAgeMonths, @Min(1) @Max(216) int maxAgeMonths) {}
    @PostMapping("/classes")
    @Operation(summary = "Create Class", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created createClass(@Valid @RequestBody CreateClassRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenEnrolChildRequest")
    record EnrolChildRequest(@NotNull UUID programmeId, @NotNull UUID childId, @NotNull LocalDate validFrom,
        @NotNull LocalDate validUntil) {}
    @PostMapping("/enrolments")
    @Operation(summary = "Enrol Child", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created enrolChild(@Valid @RequestBody EnrolChildRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenAssignClassRequest")
    record AssignClassRequest(@NotNull UUID enrolmentId, @NotNull UUID classId, @NotNull LocalDate validFrom,
        @NotNull LocalDate validUntil) {}
    @PostMapping("/class-assignments")
    @Operation(summary = "Assign Class", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created assignClass(@Valid @RequestBody AssignClassRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenEndEnrolmentRequest")
    record EndEnrolmentRequest(@NotNull UUID enrolmentId, @Min(0) long expectedVersion) {}
    @PostMapping("/enrolments/end")
    @Operation(summary = "End Enrolment", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void endEnrolment(@Valid @RequestBody EndEnrolmentRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenEndClassAssignmentRequest")
    record EndClassAssignmentRequest(@NotNull UUID assignmentId, @Min(0) long expectedVersion) {}
    @PostMapping("/class-assignments/end")
    @Operation(summary = "End Class Assignment", description = "Requires children.manage. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void endClassAssignment(@Valid @RequestBody EndClassAssignmentRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/programmes")
    @Operation(summary = "List Programmes", description = "Requires children.read. Version conflicts require reloading the current resource.")
    List<Programme> listProgrammes(@RequestParam(name = "search", defaultValue = "") String search,
        @RequestParam(name = "limit", defaultValue = "50") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset);
    @GetMapping("/programmes/{programmeId}")
    @Operation(summary = "Get Programme", description = "Requires children.read. Version conflicts require reloading the current resource.")
    Programme getProgramme(@PathVariable("programmeId") UUID programmeId);
    @GetMapping("/classes")
    @Operation(summary = "List Classes", description = "Requires children.read. Version conflicts require reloading the current resource.")
    List<MinistryClass> listClasses(@RequestParam(name = "programmeId", required = true) UUID programmeId);
    @GetMapping("/enrolments")
    @Operation(summary = "List Enrolments", description = "Requires children.read. Version conflicts require reloading the current resource.")
    List<Enrolment> listEnrolments(@RequestParam(name = "childId", required = true) UUID childId,
        @RequestParam(name = "limit", defaultValue = "50") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset);
    @GetMapping("/class-assignments")
    @Operation(summary = "List Class Assignments", description = "Requires children.read. Version conflicts require reloading the current resource.")
    List<ClassAssignment> listClassAssignments(@RequestParam(name = "childId", required = true) UUID childId,
        @RequestParam(name = "limit", defaultValue = "50") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset);
    @GetMapping("/consent-policies/{policyId}")
    @Operation(summary = "Get Consent Policy", description = "Requires children.read. Version conflicts require reloading the current resource.")
    ConsentPolicy getConsentPolicy(@PathVariable("policyId") UUID policyId);
}
