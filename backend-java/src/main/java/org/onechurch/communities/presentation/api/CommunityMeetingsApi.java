package org.onechurch.communities.presentation.api;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.events.application.contract.CommunityCalendar;
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

import java.util.UUID;
@Tag(name = "CommunityMeetings")
public interface CommunityMeetingsApi {
    record Created(UUID id) {}
    record ScheduleGroupMeetingsBody(@NotNull UUID requestId, @Min(0) long expectedVersion,
        @NotNull CommunityCalendar.Schedule schedule, @NotBlank @Size(max = 150) String reviewHash) {}
    @PostMapping("/api/communities/{communityId}/meetings")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "ScheduleGroupMeetings", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    Created scheduleGroupMeetings(@PathVariable UUID communityId, @Valid @RequestBody ScheduleGroupMeetingsBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record PreviewGroupScheduleBody(@NotNull CommunityCalendar.Schedule schedule) {}
    @PostMapping("/api/communities/{communityId}/meeting-preview")
    @Operation(summary = "PreviewGroupSchedule", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    CommunityCalendar.Preview previewGroupSchedule(@PathVariable UUID communityId,
        @Valid @RequestBody PreviewGroupScheduleBody body);
    record PrepareGroupRosterBody(@Min(0) long expectedVersion) {}
    @PostMapping("/api/communities/meetings/{meetingId}/prepare")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "PrepareGroupRoster", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    void prepareGroupRoster(@PathVariable UUID meetingId, @Valid @RequestBody PrepareGroupRosterBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record AddGroupGuestBody(@Min(0) long expectedVersion, @NotNull UUID personId,
        @NotBlank @Size(max = 2000) String reason) {}
    @PostMapping("/api/communities/meetings/{meetingId}/guests")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "AddGroupGuest", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    void addGroupGuest(@PathVariable UUID meetingId, @Valid @RequestBody AddGroupGuestBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record RecordGroupAttendanceBody(@Min(0) long expectedVersion, @NotBlank @Size(max = 150) String status,
        @NotBlank @Size(max = 2000) String reason) {}
    @PutMapping("/api/communities/roster/{rosterId}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "RecordGroupAttendance", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    void recordGroupAttendance(@PathVariable UUID rosterId, @Valid @RequestBody RecordGroupAttendanceBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    record FinishGroupMeetingBody(@Min(0) long expectedVersion, @NotBlank @Size(max = 150) String status,
        @NotBlank @Size(max = 2000) String reason) {}
    @PostMapping("/api/communities/meetings/{meetingId}/finish")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "FinishGroupMeeting", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    void finishGroupMeeting(@PathVariable UUID meetingId, @Valid @RequestBody FinishGroupMeetingBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping("/api/communities/{communityId}/meetings")
    @Operation(summary = "ListGroupMeetings", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    CommunityViews.Meetings listGroupMeetings(@PathVariable UUID communityId,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/communities/meetings/{meetingId}/roster")
    @Operation(summary = "GetGroupRoster", description = "Live application permissions and current leadership scope are enforced. Changes use optimistic versions; membership and leadership take effect immediately.")
    CommunityViews.Roster getGroupRoster(@PathVariable UUID meetingId, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
}
