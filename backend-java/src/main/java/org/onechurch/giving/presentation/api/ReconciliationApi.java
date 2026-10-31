package org.onechurch.giving.presentation.api;
import org.onechurch.giving.application.contract.ReconciliationReader;
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
@Tag(name = "Giving · Reconciliation")
public interface ReconciliationApi {
    record Created(UUID id) {}
    record ReconcileBody(@NotNull UUID requestId, @NotBlank @Size(max = 200) String reference,
        @NotNull @DecimalMin("1") @DecimalMax("100000000000000") @Digits(integer = 15, fraction = 0) @Schema(implementation = Long.class, types = "integer", format = "int64") BigDecimal expectedMinor,
        @NotBlank String currency, @NotNull LocalDate reconciledOn, @Size(max = 1000) String reason,
        @NotNull @Size(min = 1, max = 100) List<@NotNull UUID> giftIds) {}
    record ReviewBody(@NotNull UUID requestId, @Min(0) long expectedVersion, @NotBlank String outcome,
        @NotBlank @Size(max = 1000) String note) {}
    @PostMapping(value = "/api/giving/reconciliations")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "createReconciliation", description = "Freeze a bank or cash batch of 1–100 distinct unreversed gifts in one currency. Gifts may belong to only one batch. A mismatch needs an explanation and remains visible for review; later reversals preserve the original batch.")
    Created createReconciliation(@Valid @RequestBody ReconcileBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping(value = "/api/giving/reconciliations/{batchId}/reviews")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "reviewReconciliation", description = "Append versioned FOLLOW_UP or EXPLAINED evidence. Source gifts are locked during review. A later reversal invalidates the reviewed financial state and reopens the review flag.")
    Created reviewReconciliation(@PathVariable UUID batchId, @Valid @RequestBody ReviewBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping(value = "/api/giving/reconciliations")

    @Operation(summary = "listReconciliations", description = "Paged frozen batches with current reversal totals and explicit needsReview flags.")
    ReconciliationReader.Page listReconciliations(@RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping(value = "/api/giving/reconciliations/{batchId}")

    @Operation(summary = "getReconciliation", description = "Original batch amounts, selected gifts and latest 100 reviews. Reconciliation never changes posted gifts or claims a provider settlement.")
    ReconciliationReader.Detail getReconciliation(@PathVariable UUID batchId);
}
