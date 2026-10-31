package org.onechurch.communities.presentation;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.communities.application.command.addgroupguest.AddGroupGuestCommand;
import org.onechurch.communities.application.command.addgroupguest.AddGroupGuestHandler;
import org.onechurch.communities.application.command.finishgroupmeeting.FinishGroupMeetingCommand;
import org.onechurch.communities.application.command.finishgroupmeeting.FinishGroupMeetingHandler;
import org.onechurch.communities.application.command.preparegrouproster.PrepareGroupRosterCommand;
import org.onechurch.communities.application.command.preparegrouproster.PrepareGroupRosterHandler;
import org.onechurch.communities.application.command.recordgroupattendance.RecordGroupAttendanceCommand;
import org.onechurch.communities.application.command.recordgroupattendance.RecordGroupAttendanceHandler;
import org.onechurch.communities.application.command.schedulegroupmeetings.ScheduleGroupMeetingsCommand;
import org.onechurch.communities.application.command.schedulegroupmeetings.ScheduleGroupMeetingsHandler;
import org.onechurch.communities.application.query.getgrouproster.GetGroupRosterHandler;
import org.onechurch.communities.application.query.getgrouproster.GetGroupRosterQuery;
import org.onechurch.communities.application.query.listgroupmeetings.ListGroupMeetingsHandler;
import org.onechurch.communities.application.query.listgroupmeetings.ListGroupMeetingsQuery;
import org.onechurch.communities.application.query.previewgroupschedule.PreviewGroupScheduleHandler;
import org.onechurch.communities.application.query.previewgroupschedule.PreviewGroupScheduleQuery;
import org.onechurch.communities.presentation.api.CommunityMeetingsApi;
import org.onechurch.events.application.contract.CommunityCalendar;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
@PreAuthorize("hasAnyAuthority('ministries.read','ministries.manage','ministries.lead')")
public class CommunityMeetingsController implements CommunityMeetingsApi {
    private final ScheduleGroupMeetingsHandler scheduleGroupMeetingsHandler;
    private final PreviewGroupScheduleHandler previewGroupScheduleHandler;
    private final PrepareGroupRosterHandler prepareGroupRosterHandler;
    private final AddGroupGuestHandler addGroupGuestHandler;
    private final RecordGroupAttendanceHandler recordGroupAttendanceHandler;
    private final FinishGroupMeetingHandler finishGroupMeetingHandler;
    private final ListGroupMeetingsHandler listGroupMeetingsHandler;
    private final GetGroupRosterHandler getGroupRosterHandler;
    public CommunityMeetingsController(ScheduleGroupMeetingsHandler scheduleGroupMeetingsHandler,
        PreviewGroupScheduleHandler previewGroupScheduleHandler, PrepareGroupRosterHandler prepareGroupRosterHandler,
        AddGroupGuestHandler addGroupGuestHandler, RecordGroupAttendanceHandler recordGroupAttendanceHandler,
        FinishGroupMeetingHandler finishGroupMeetingHandler, ListGroupMeetingsHandler listGroupMeetingsHandler,
        GetGroupRosterHandler getGroupRosterHandler) {
        this.scheduleGroupMeetingsHandler = scheduleGroupMeetingsHandler;
        this.previewGroupScheduleHandler = previewGroupScheduleHandler;
        this.prepareGroupRosterHandler = prepareGroupRosterHandler;
        this.addGroupGuestHandler = addGroupGuestHandler;
        this.recordGroupAttendanceHandler = recordGroupAttendanceHandler;
        this.finishGroupMeetingHandler = finishGroupMeetingHandler;
        this.listGroupMeetingsHandler = listGroupMeetingsHandler;
        this.getGroupRosterHandler = getGroupRosterHandler;
    }
    @Override
    public Created scheduleGroupMeetings(UUID communityId, ScheduleGroupMeetingsBody body,
        OAuth2AuthenticatedPrincipal actor) {
        return new Created(
            scheduleGroupMeetingsHandler.handle(new ScheduleGroupMeetingsCommand(UUID.fromString(actor.getName()),
                body.requestId(), communityId, body.expectedVersion(), body.schedule(), body.reviewHash())));
    }
    @Override
    public CommunityCalendar.Preview previewGroupSchedule(UUID communityId, PreviewGroupScheduleBody body) {
        return previewGroupScheduleHandler.handle(new PreviewGroupScheduleQuery(communityId, body.schedule()));
    }
    @Override
    public void prepareGroupRoster(UUID meetingId, PrepareGroupRosterBody body, OAuth2AuthenticatedPrincipal actor) {
        prepareGroupRosterHandler
            .handle(new PrepareGroupRosterCommand(UUID.fromString(actor.getName()), meetingId, body.expectedVersion()));
    }
    @Override
    public void addGroupGuest(UUID meetingId, AddGroupGuestBody body, OAuth2AuthenticatedPrincipal actor) {
        addGroupGuestHandler.handle(new AddGroupGuestCommand(UUID.fromString(actor.getName()), meetingId,
            body.expectedVersion(), body.personId(), body.reason()));
    }
    @Override
    public void recordGroupAttendance(UUID rosterId, RecordGroupAttendanceBody body,
        OAuth2AuthenticatedPrincipal actor) {
        recordGroupAttendanceHandler.handle(new RecordGroupAttendanceCommand(UUID.fromString(actor.getName()), rosterId,
            body.expectedVersion(), body.status(), body.reason()));
    }
    @Override
    public void finishGroupMeeting(UUID meetingId, FinishGroupMeetingBody body, OAuth2AuthenticatedPrincipal actor) {
        finishGroupMeetingHandler.handle(new FinishGroupMeetingCommand(UUID.fromString(actor.getName()), meetingId,
            body.expectedVersion(), body.status(), body.reason()));
    }
    @Override
    public CommunityViews.Meetings listGroupMeetings(UUID communityId, int limit, int offset) {
        return listGroupMeetingsHandler.handle(new ListGroupMeetingsQuery(communityId, limit, offset));
    }
    @Override
    public CommunityViews.Roster getGroupRoster(UUID meetingId, int limit, int offset) {
        return getGroupRosterHandler.handle(new GetGroupRosterQuery(meetingId, limit, offset));
    }
}
