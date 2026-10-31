package org.onechurch.communities.presentation.api;
import org.onechurch.communities.application.contract.CommunityReader;
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
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
@Tag(name = "CommunityFollowUps")
public interface CommunityFollowUpsApi {
    record Created(UUID id) {}
    record CreateCommunityFollowUpBody(@NotNull UUID requestId, @NotNull UUID personId,
        @NotBlank @Size(max = 2000) String note, @NotNull LocalDate dueOn) {}
    @PostMapping("/api/communities/{communityId}/follow-ups")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "CreateCommunityFollowUp", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    Created createCommunityFollowUp(@PathVariable UUID communityId,
        @Valid @RequestBody CreateCommunityFollowUpBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record CompleteCommunityFollowUpBody(@Min(0) long expectedVersion, @NotBlank @Size(max = 2000) String reason) {}
    @PostMapping("/api/communities/follow-ups/{followUpId}/complete")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "CompleteCommunityFollowUp", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    void completeCommunityFollowUp(@PathVariable UUID followUpId,
        @Valid @RequestBody CompleteCommunityFollowUpBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping("/api/communities/{communityId}/follow-ups")
    @Operation(summary = "ListCommunityFollowUps", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    List<CommunityReader.FollowUp> listCommunityFollowUps(@PathVariable UUID communityId,
        @RequestParam(defaultValue = "false") boolean completed, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
}
