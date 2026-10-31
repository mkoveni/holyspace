package org.onechurch.communities.presentation.api;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.communities.application.contract.CommunityParticipation;
import org.onechurch.communities.application.contract.CommunityReader;
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
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;
@Tag(name = "CommunityDirectory")
public interface CommunityDirectoryApi {
    @GetMapping("/api/communities/people/{personId}")
    @Operation(summary = "Read a person's community participation", description = "Requires people.read and ministries.read. Paged current and historical membership links contain no meeting rosters or care information.")
    List<CommunityParticipation.Entry> personCommunities(@PathVariable UUID personId,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);

    record Created(UUID id) {}
    record CreateCommunityBody(@NotNull UUID requestId, @NotBlank @Size(max = 150) String kind,
        @NotBlank @Size(max = 150) String name, @Size(max = 5000) String description, UUID parentId,
        @Size(max = 150) String campus) {}
    @PostMapping("/api/communities")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "CreateCommunity", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    Created createCommunity(@Valid @RequestBody CreateCommunityBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record UpdateCommunityBody(@Min(0) long expectedVersion, @NotBlank @Size(max = 150) String name,
        @Size(max = 5000) String description, UUID parentId, @Size(max = 150) String campus, boolean active,
        @NotBlank @Size(max = 2000) String reason) {}
    @PutMapping("/api/communities/{communityId}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "UpdateCommunity", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    void updateCommunity(@PathVariable UUID communityId, @Valid @RequestBody UpdateCommunityBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record JoinCommunityBody(@NotNull UUID requestId, @NotNull UUID personId) {}
    @PostMapping("/api/communities/{communityId}/members")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "JoinCommunity", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    Created joinCommunity(@PathVariable UUID communityId, @Valid @RequestBody JoinCommunityBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record LeaveCommunityBody(@Min(0) long expectedVersion, @NotBlank @Size(max = 2000) String reason) {}
    @PostMapping("/api/communities/memberships/{membershipId}/leave")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "LeaveCommunity", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    void leaveCommunity(@PathVariable UUID membershipId, @Valid @RequestBody LeaveCommunityBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record AppointCommunityLeaderBody(@NotNull UUID requestId, @Min(0) long expectedVersion,
        @NotBlank @Size(max = 150) String role, UUID accountId, @NotBlank @Size(max = 2000) String reason) {}
    @PostMapping("/api/communities/memberships/{membershipId}/leadership")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "AppointCommunityLeader", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    Created appointCommunityLeader(@PathVariable UUID membershipId, @Valid @RequestBody AppointCommunityLeaderBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record EndCommunityLeadershipBody(@Min(0) long expectedVersion, @NotBlank @Size(max = 2000) String reason) {}
    @PostMapping("/api/communities/leadership/{leadershipId}/end")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "EndCommunityLeadership", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    void endCommunityLeadership(@PathVariable UUID leadershipId, @Valid @RequestBody EndCommunityLeadershipBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping("/api/communities")
    @Operation(summary = "ListCommunities", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    CommunityViews.Directory listCommunities(@RequestParam(required = false) String kind,
        @RequestParam(required = false) String search, @RequestParam(defaultValue = "false") boolean archived,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/communities/{communityId}")
    @Operation(summary = "GetCommunity", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    CommunityViews.Details getCommunity(@PathVariable UUID communityId);
    @GetMapping("/api/communities/{communityId}/members")
    @Operation(summary = "ListCommunityMembers", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    CommunityViews.Members listCommunityMembers(@PathVariable UUID communityId,
        @RequestParam(defaultValue = "false") boolean ended, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/communities/{communityId}/leadership")
    @Operation(summary = "ListCommunityLeadership", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    List<CommunityViews.Appointment> listCommunityLeadership(@PathVariable UUID communityId,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/communities/{communityId}/history")
    @Operation(summary = "GetCommunityHistory", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    List<CommunityReader.History> getCommunityHistory(@PathVariable UUID communityId,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
}
