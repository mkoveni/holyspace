package org.onechurch.giving.presentation.api;
import org.onechurch.giving.application.query.listdonoraccess.ListDonorAccessHandler;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
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
@Tag(name = "Donor access", description = "Explicitly verified account links and opt-in online funds. Identity and Giving management are both required for account links.")
public interface DonorAccessApi {
    record LinkBody(@NotNull UUID personId, boolean active, @Min(0) long expectedVersion,
        @NotBlank @Size(max = 1000) String evidence) {}
    record FundBody(boolean enabled, @Min(0) long expectedVersion) {}
    @GetMapping("/api/giving/donor-access")
    @Operation(summary = "Read verified donor access and online fund configuration")
    ResponseEntity<ListDonorAccessHandler.Result> list(@RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @PutMapping("/api/giving/donor-access/{accountId}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Verify, revoke or restore financial identity", description = "Requires identity.manage and giving.manage. Verify the account holder's identity offline and record evidence. Account and person links are unique and cannot be reassigned. Version 0 creates a link; later changes require its current version.")
    void link(@PathVariable UUID accountId, @Valid @RequestBody LinkBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PutMapping("/api/giving/online-funds/{fundId}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Offer or withdraw a fund from donor self-service", description = "Requires giving.manage and the fund's current version. Active funds are not automatically offered online.")
    void fund(@PathVariable UUID fundId, @Valid @RequestBody FundBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
