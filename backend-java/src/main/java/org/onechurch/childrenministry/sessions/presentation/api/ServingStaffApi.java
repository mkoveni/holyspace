package org.onechurch.childrenministry.sessions.presentation.api;

import org.onechurch.childrenministry.sessions.application.query.listservingcandidates.ListServingCandidatesHandler;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;

import java.util.List;
import java.util.UUID;

@Tag(name = "Children sessions")
public interface ServingStaffApi {
    record Import(@Min(0) long expectedVersion, @NotNull UUID assignmentId, @NotBlank String responsibility,
        boolean present) {}
    @GetMapping("/api/children/sessions/{id}/serving-candidates")
    @Operation(summary = "List serving assignments for this exact session", description = "Requires children.manage. Includes readiness issues and whether the person has a Children Ministry worker record.")
    List<ListServingCandidatesHandler.Candidate> candidates(@PathVariable UUID id,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/children/sessions/{id}/serving-staff")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Import a confirmed, ready serving assignment", description = "Requires children.manage. Locks the person before the session. Retains Children Ministry worker verification and ongoing readiness checks; confirmation alone never grants clearance.")
    void add(@PathVariable UUID id, @Valid @RequestBody Import body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
