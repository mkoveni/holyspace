package org.onechurch.communities.presentation.api;
import org.onechurch.communities.application.MemberCommunityViews;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.util.UUID;
@Tag(name = "My groups and ministries")
public interface MemberCommunitiesApi {
    @GetMapping("/api/member/communities")
    @Operation(summary = "List current own group and ministry memberships", description = "Uses verified member identity and current membership on an active community. Unverified accounts receive an empty unverified result. No directory or staff permission is granted.")
    MemberCommunityViews.MemberCommunitiesPage list(@RequestParam(required = false) String kind,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/member/communities/{id}")
    @Operation(summary = "Read an own community and current leaders", description = "Leader names and roles only, with bounded pagination. Parent ministry names do not grant parent membership or access. No contacts, account bindings, rosters or care notes.")
    MemberCommunityViews.MemberCommunityDetails detail(@PathVariable UUID id,
        @RequestParam(defaultValue = "25") int leaderLimit, @RequestParam(defaultValue = "0") int leaderOffset);
    @GetMapping("/api/member/group-meetings")
    @Operation(summary = "List upcoming meetings in current own communities", description = "Upcoming 180 days. Optional community scope must belong to the caller. Includes own attendance only and current schedule/cancellation state. GET never creates or freezes attendance rosters.")
    MemberCommunityViews.MemberGroupMeetingsPage meetings(@RequestParam(required = false) UUID communityId,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/member/group-meetings/{id}")
    @Operation(summary = "Read an own group meeting and personal attendance", description = "Requires current membership. Meetings predating membership are hidden unless the caller has their own attendance row. Unrecorded, not prepared, not listed and absent remain distinct. This does not grant event check-in or child collection authority.")
    MemberCommunityViews.MemberGroupMeeting meeting(@PathVariable UUID id);
}
