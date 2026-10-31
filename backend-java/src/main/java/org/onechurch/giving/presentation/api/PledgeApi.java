package org.onechurch.giving.presentation.api;
import org.onechurch.giving.application.contract.PledgeReader;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.DecimalMax;
import jakarta.validation.constraints.DecimalMin;
import jakarta.validation.constraints.Digits;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
@Tag(name = "Giving · Pledge")
public interface PledgeApi {
    record Created(UUID id) {}
    record CreatePledgeBody(@NotNull UUID requestId, @NotNull UUID donorId, @NotNull UUID fundId,
        @NotNull @DecimalMin("1") @DecimalMax("1000000000000") @Digits(integer = 13, fraction = 0) @Schema(implementation = Long.class, types = "integer", format = "int64") BigDecimal amountMinor,
        @NotBlank String currency, @NotNull LocalDate startsOn, @NotNull LocalDate endsOn) {}
    record CancelBody(@Min(0) long expectedVersion, @NotBlank @Size(max = 1000) String reason) {}
    record AssignBody(@NotNull UUID requestId, @NotNull UUID giftId, @Min(0) long expectedVersion,
        @NotNull @DecimalMin("1") @DecimalMax("1000000000000") @Digits(integer = 13, fraction = 0) @Schema(implementation = Long.class, types = "integer", format = "int64") BigDecimal amountMinor) {}
    @PostMapping(value = "/api/giving/pledges")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "createPledge", description = "Create a commitment. Gifts never fulfil it automatically; assignments must match donor, fund, currency and received-date period.")
    Created createPledge(@Valid @RequestBody CreatePledgeBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping(value = "/api/giving/pledges/{pledgeId}/cancel")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "cancelPledge", description = "Cancel with the current version and a reason. Existing assignments and financial evidence remain.")
    void cancelPledge(@PathVariable UUID pledgeId, @Valid @RequestBody CancelBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping(value = "/api/giving/pledges/{pledgeId}/assignments")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "assignPledgeGift", description = "Explicitly assign available gift fund allocations. Locks serialize gift and target budgets; reversed gifts cannot be assigned.")
    Created assignPledgeGift(@PathVariable UUID pledgeId, @Valid @RequestBody AssignBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping(value = "/api/giving/pledge-assignments/{assignmentId}/cancel")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "cancelPledgeAssignment", description = "Cancel a versioned assignment with a reason, preserving its history and releasing the gift allocation.")
    void cancelPledgeAssignment(@PathVariable UUID assignmentId, @Valid @RequestBody CancelBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping(value = "/api/giving/pledges")

    @Operation(summary = "listPledges", description = "Current progress excludes cancelled assignments and all reversed gifts. Lists are paged; cancelled commitments retain history.")
    PledgeReader.Page listPledges(@RequestParam(required = false) UUID donorId,
        @RequestParam(required = false) String status, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping(value = "/api/giving/pledges/{pledgeId}")

    @Operation(summary = "getPledge", description = "Current progress, paged assignment history and latest 100 audit entries. This is a live projection, not a historical statement.")
    PledgeReader.Detail getPledge(@PathVariable UUID pledgeId, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping(value = "/api/giving/pledges/{pledgeId}/candidates")

    @Operation(summary = "listPledgeGiftCandidates", description = "Paged eligible gifts and their currently unassigned fund amounts. Eligibility is checked again when assigning.")
    List<PledgeReader.Candidate> listPledgeGiftCandidates(@PathVariable UUID pledgeId,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
}
