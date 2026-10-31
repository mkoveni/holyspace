package org.onechurch.giving.presentation.api;
import org.onechurch.giving.application.contract.AcknowledgementReader;
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
import jakarta.validation.constraints.NotNull;

import java.util.UUID;
@Tag(name = "Giving · Acknowledgement")
public interface AcknowledgementApi {
    record Created(UUID id) {}
    record IssueBody(@NotNull UUID requestId) {}
    @PostMapping(value = "/api/giving/gifts/{giftId}/acknowledgements")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "issueAcknowledgement", description = "Issue one immutable numbered acknowledgement per gift. Donor and fund names are frozen at issue. Reversal makes it VOID without reusing the number. Number sequences may contain gaps; no tax-deductibility claim is made.")
    Created issueAcknowledgement(@PathVariable UUID giftId, @Valid @RequestBody IssueBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping(value = "/api/giving/gifts/{giftId}/acknowledgement")

    @Operation(summary = "getGiftAcknowledgement", description = "Read the existing acknowledgement without issuing or reissuing it. The acknowledgement property is null when none exists.")
    AcknowledgementReader.Status getGiftAcknowledgement(@PathVariable UUID giftId);
    @GetMapping(value = "/api/giving/acknowledgements/{acknowledgementId}")

    @Operation(summary = "getAcknowledgement", description = "Read printable frozen issue evidence with its current VOID status and reversal reason.")
    AcknowledgementReader.View getAcknowledgement(@PathVariable UUID acknowledgementId);
}
