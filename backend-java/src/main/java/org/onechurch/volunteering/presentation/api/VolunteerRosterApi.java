package org.onechurch.volunteering.presentation.api;

import org.onechurch.volunteering.application.RosterPlanning;
import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.application.VolunteerReadiness;
import org.onechurch.volunteering.application.contract.ActivitySource;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;

import java.time.Instant;
import java.util.List;
import java.util.UUID;

@Tag(name = "Volunteers and serving teams")
public interface VolunteerRosterApi extends VolunteeringApi {
    @PostMapping("/api/volunteering/shifts")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "createShift", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created createShift(@Valid @RequestBody RosterPlanning.Draft b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/shifts/bulk")
    @Operation(summary = "bulkShifts", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    List<UUID> bulkShifts(@Valid @RequestBody List<RosterPlanning.Draft> b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PutMapping("/api/volunteering/shifts/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "updateShift", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    void updateShift(@PathVariable UUID id, @Valid @RequestBody ShiftEdit b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/assignments")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "assign", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created assign(@Valid @RequestBody Assign b, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/assignments/{id}/status")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "changeAssignment", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    void changeAssignment(@PathVariable UUID id, @Valid @RequestBody AssignmentEdit b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @GetMapping("/api/volunteering/shifts")
    @Operation(summary = "shifts", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    ServingViews.Shifts shifts(@RequestParam Instant from, @RequestParam Instant until,
        @RequestParam(required = false) UUID teamId, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/volunteering/shifts/{id}")
    @Operation(summary = "shift", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    ServingViews.ShiftView shift(@PathVariable UUID id);
    @GetMapping("/api/volunteering/readiness")
    @Operation(summary = "readiness", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    VolunteerReadiness.Assessment readiness(@RequestParam UUID volunteerId, @RequestParam UUID shiftId);
    @GetMapping("/api/volunteering/activities")
    @Operation(summary = "activities", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    List<ActivitySource.Activity> activities(@RequestParam String kind, @RequestParam Instant from,
        @RequestParam Instant until, @RequestParam(defaultValue = "100") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/volunteering/assignments/{id}/events")
    @Operation(summary = "history", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    List<RosterReader.Event> history(@PathVariable UUID id, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
}
