package org.onechurch.giving.presentation.api;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.domain.Gift;
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
@Tag(name = "Giving", description = "Confidential manual giving ledger. Posted gifts are immutable; reversals are separate dated evidence. No payment is collected by these endpoints.")
public interface GivingApi {
    record Created(UUID id) {}
    record CreateFundBody(@NotNull UUID requestId, @NotBlank @Size(max = 100) String name,
        @Size(max = 1000) String description) {}
    record UpdateFundBody(@Min(0) long expectedVersion, @NotBlank @Size(max = 100) String name,
        @Size(max = 1000) String description, boolean active, @NotBlank @Size(max = 1000) String reason) {}
    record AllocationBody(@NotNull UUID fundId,
        @NotNull @DecimalMin("1") @DecimalMax("1000000000000") @Digits(integer = 13, fraction = 0) @Schema(implementation = Long.class, type = "integer", types = {
            "integer"}, format = "int64") BigDecimal amountMinor){
        public Gift.Allocation allocation() {
            return new Gift.Allocation(fundId, amountMinor.longValueExact());
        }
    }
    record RecordGiftBody(@NotNull UUID requestId, UUID donorId,
        @NotNull @DecimalMin("1") @DecimalMax("1000000000000") @Digits(integer = 13, fraction = 0) @Schema(implementation = Long.class, type = "integer", types = {
            "integer"}, format = "int64") BigDecimal amountMinor,
        @NotBlank String currency, @NotNull LocalDate receivedOn, @NotBlank String method,
        @Size(max = 200) String externalReference,
        @NotNull @Size(min = 1, max = 20) List<@NotNull @Valid AllocationBody> allocations){}
    record ReverseGiftBody(@NotNull UUID requestId, @NotNull LocalDate reversedOn,
        @NotBlank @Size(max = 1000) String reason) {}
    @GetMapping("/api/giving/funds")
    @Operation(summary = "Browse funds", description = "Requires giving.read or giving.manage. Archived funds remain available in historical gift details.")
    GivingReader.FundPage funds(@RequestParam(defaultValue = "false") boolean archived,
        @RequestParam(required = false) String search, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @PostMapping("/api/giving/funds")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Create a fund", description = "Requires giving.manage. Request IDs make retries safe; names are unique ignoring case and surrounding spaces.")
    Created createFund(@Valid @RequestBody CreateFundBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PutMapping("/api/giving/funds/{fundId}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Update or archive a fund", description = "Requires giving.manage, the current version and a reason. Archiving prevents new gifts without changing past allocations.")
    void updateFund(@PathVariable UUID fundId, @Valid @RequestBody UpdateFundBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping("/api/giving/gifts")
    @Operation(summary = "Browse received gifts", description = "Requires giving.read or giving.manage. Inclusive received-date range, at most ten years, bounded pages. Fund filtering selects gifts containing that fund; amounts remain the whole gift.")
    GivingReader.GiftPage gifts(@RequestParam LocalDate from, @RequestParam LocalDate to,
        @RequestParam(required = false) String currency, @RequestParam(required = false) UUID fundId,
        @RequestParam(required = false) UUID donorId, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/giving/gifts/{giftId}")
    @Operation(summary = "Read gift evidence", description = "Requires giving.read or giving.manage. Includes fund allocations, full reversal and actor/reason history. Does not issue a tax receipt.")
    GivingReader.Detail gift(@PathVariable UUID giftId);
    @PostMapping("/api/giving/gifts")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Post a manual gift", description = "Requires giving.manage. Amounts are integer minor units. ZAR, USD, EUR and GBP use two decimals. Allocate the entire gift to 1–20 active funds. No donor means anonymous. CASH, BANK and OTHER only; this never asserts online payment. Dates are no later than today in the church timezone. Reuse the request ID only for an identical retry.")
    Created recordGift(@Valid @RequestBody RecordGiftBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/giving/gifts/{giftId}/reversals")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Record a full gift reversal", description = "Requires giving.reverse separately. One immutable full reversal per gift; date must be between received date and today. This corrects the ledger and does not refund a payment provider.")
    Created reverseGift(@PathVariable UUID giftId, @Valid @RequestBody ReverseGiftBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping("/api/giving/totals")
    @Operation(summary = "Read period totals", description = "Requires giving.read or giving.manage. Totals cover the entire filtered period, not one page. Gross gifts use received dates; reversals use reversal dates, so net may be negative. Fund totals use only that fund's allocation. Currency totals are separate; aggregate minor units are decimal strings to preserve precision.")
    List<GivingReader.Total> totals(@RequestParam LocalDate from, @RequestParam LocalDate to,
        @RequestParam(required = false) String currency, @RequestParam(required = false) UUID fundId,
        @RequestParam(required = false) UUID donorId);
}
