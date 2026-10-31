package org.onechurch.communities.presentation.api;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communities.application.CommunityMessaging;
import org.onechurch.people.application.contract.PersonEmailDirectory;
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
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;
@Tag(name = "CommunityMessaging")
public interface CommunityMessagingApi {
    @GetMapping("/api/communities/{communityId}/deliveries")
    @Operation(summary = "Review community delivery outcomes", description = "Requires current community scope and ministries.communications.send. Returns no email addresses or message content.")
    List<DeliveryReader.Summary> deliveries(@PathVariable UUID communityId,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);

    record Created(UUID id) {}
    record ApproveCommunityNoticeBody(@NotNull UUID requestId, @NotNull UUID contactId,
        @NotBlank @Size(max = 5000) String message, @NotBlank @Size(max = 150) String reviewHash,
        @NotBlank @Size(max = 500) String evidence) {}
    @PostMapping("/api/communities/memberships/{membershipId}/notices")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "ApproveCommunityNotice", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    Created approveCommunityNotice(@PathVariable UUID membershipId, @Valid @RequestBody ApproveCommunityNoticeBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping("/api/communities/memberships/{membershipId}/contacts")
    @Operation(summary = "ListCommunityContacts", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    List<PersonEmailDirectory.Email> listCommunityContacts(@PathVariable UUID membershipId);
    record ReviewCommunityNoticeBody(@NotNull UUID contactId, @NotBlank @Size(max = 5000) String message) {}
    @PostMapping("/api/communities/memberships/{membershipId}/notice-review")
    @Operation(summary = "ReviewCommunityNotice", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    CommunityMessaging.Review reviewCommunityNotice(@PathVariable UUID membershipId,
        @Valid @RequestBody ReviewCommunityNoticeBody body);
}
