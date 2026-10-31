package org.onechurch.communities.infrastructure;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.communities.application.CommunityAccess;
import org.onechurch.communities.application.CommunityManagement;
import org.onechurch.communities.application.CommunityMessaging;
import org.onechurch.communities.application.CommunityViews;
import org.onechurch.communities.application.GroupMeetings;
import org.onechurch.communities.application.command.addgroupguest.AddGroupGuestHandler;
import org.onechurch.communities.application.command.appointcommunityleader.AppointCommunityLeaderHandler;
import org.onechurch.communities.application.command.approvecommunitynotice.ApproveCommunityNoticeHandler;
import org.onechurch.communities.application.command.completecommunityfollowup.CompleteCommunityFollowUpHandler;
import org.onechurch.communities.application.command.createcommunity.CreateCommunityHandler;
import org.onechurch.communities.application.command.createcommunityfollowup.CreateCommunityFollowUpHandler;
import org.onechurch.communities.application.command.endcommunityleadership.EndCommunityLeadershipHandler;
import org.onechurch.communities.application.command.finishgroupmeeting.FinishGroupMeetingHandler;
import org.onechurch.communities.application.command.joincommunity.JoinCommunityHandler;
import org.onechurch.communities.application.command.leavecommunity.LeaveCommunityHandler;
import org.onechurch.communities.application.command.preparegrouproster.PrepareGroupRosterHandler;
import org.onechurch.communities.application.command.recordgroupattendance.RecordGroupAttendanceHandler;
import org.onechurch.communities.application.command.schedulegroupmeetings.ScheduleGroupMeetingsHandler;
import org.onechurch.communities.application.command.updatecommunity.UpdateCommunityHandler;
import org.onechurch.communities.application.contract.CommunityParticipation;
import org.onechurch.communities.application.contract.CommunityReader;
import org.onechurch.communities.application.contract.CommunityWriter;
import org.onechurch.communities.application.query.getcommunity.GetCommunityHandler;
import org.onechurch.communities.application.query.getcommunityhistory.GetCommunityHistoryHandler;
import org.onechurch.communities.application.query.getgrouproster.GetGroupRosterHandler;
import org.onechurch.communities.application.query.listcommunities.ListCommunitiesHandler;
import org.onechurch.communities.application.query.listcommunitycontacts.ListCommunityContactsHandler;
import org.onechurch.communities.application.query.listcommunitydeliveries.ListCommunityDeliveriesHandler;
import org.onechurch.communities.application.query.listcommunityfollowups.ListCommunityFollowUpsHandler;
import org.onechurch.communities.application.query.listcommunityleadership.ListCommunityLeadershipHandler;
import org.onechurch.communities.application.query.listcommunitymembers.ListCommunityMembersHandler;
import org.onechurch.communities.application.query.listgroupmeetings.ListGroupMeetingsHandler;
import org.onechurch.communities.application.query.listpersoncommunities.ListPersonCommunitiesHandler;
import org.onechurch.communities.application.query.previewgroupschedule.PreviewGroupScheduleHandler;
import org.onechurch.communities.application.query.reviewcommunitynotice.ReviewCommunityNoticeHandler;
import org.onechurch.communities.domain.repository.CommunityRepository;
import org.onechurch.events.application.contract.CommunityCalendar;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.AccountNames;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class CommunitiesConfiguration {
    @Bean
    ListPersonCommunitiesHandler listPersonCommunitiesHandler(CommunityParticipation reader, PermissionChecker auth) {
        return new ListPersonCommunitiesHandler(reader, auth);
    }

    @Bean
    ListCommunityDeliveriesHandler listCommunityDeliveriesHandler(CommunityMessaging messaging,
        PermissionChecker auth) {
        return new ListCommunityDeliveriesHandler(messaging, auth);
    }

    @Bean
    CommunityAccess communityAccess(PermissionChecker auth, CurrentActor actors, CommunityReader reader, Clock clock) {
        return new CommunityAccess(auth, actors, reader, clock);
    }
    @Bean
    CommunityManagement communityManagement(CommunityReader reader, CommunityWriter writer,
        CommunityRepository repository, PersonDirectory people, AccountDirectory accounts, PermissionChecker auth,
        CommunityAccess access, UnitOfWork tx, Clock clock) {
        return new CommunityManagement(reader, writer, repository, people, accounts, auth, access, tx, clock);
    }
    @Bean
    CommunityViews communityViews(CommunityReader reader, CommunityAccess access, CommunityCalendar calendar,
        AccountNames names, Clock clock, PersonDirectory people) {
        return new CommunityViews(reader, access, calendar, names, clock, people);
    }
    @Bean
    GroupMeetings groupMeetings(CommunityReader reader, CommunityWriter writer, CommunityRepository repository,
        CommunityCalendar calendar, CommunityAccess access, PersonDirectory people, UnitOfWork tx, Clock clock) {
        return new GroupMeetings(reader, writer, repository, calendar, access, people, tx, clock);
    }
    @Bean
    CommunityMessaging communityMessaging(CommunityReader reader, CommunityWriter writer,
        CommunityRepository repository, CommunityAccess access, PersonDirectory people, PersonEmailDirectory emails,
        EmailTemplates templates, EmailDeliveries deliveries, UnitOfWork tx, Clock clock,
        @Qualifier("communicationDeliveryStore") DeliveryReader deliveryReader) {
        return new CommunityMessaging(reader, writer, repository, access, people, emails, templates, deliveries, tx,
            clock, deliveryReader);
    }
    @Bean
    CreateCommunityHandler createCommunityHandler(CommunityManagement service, CommunityAccess access,
        PermissionChecker auth) {
        return new CreateCommunityHandler(service, access, auth);
    }
    @Bean
    UpdateCommunityHandler updateCommunityHandler(CommunityManagement service, CommunityAccess access,
        PermissionChecker auth) {
        return new UpdateCommunityHandler(service, access, auth);
    }
    @Bean
    JoinCommunityHandler joinCommunityHandler(CommunityManagement service, CommunityAccess access,
        PermissionChecker auth) {
        return new JoinCommunityHandler(service, access, auth);
    }
    @Bean
    LeaveCommunityHandler leaveCommunityHandler(CommunityManagement service, CommunityAccess access,
        PermissionChecker auth) {
        return new LeaveCommunityHandler(service, access, auth);
    }
    @Bean
    AppointCommunityLeaderHandler appointCommunityLeaderHandler(CommunityManagement service, CommunityAccess access,
        PermissionChecker auth) {
        return new AppointCommunityLeaderHandler(service, access, auth);
    }
    @Bean
    EndCommunityLeadershipHandler endCommunityLeadershipHandler(CommunityManagement service, CommunityAccess access,
        PermissionChecker auth) {
        return new EndCommunityLeadershipHandler(service, access, auth);
    }
    @Bean
    ScheduleGroupMeetingsHandler scheduleGroupMeetingsHandler(GroupMeetings service, CommunityAccess access,
        PermissionChecker auth) {
        return new ScheduleGroupMeetingsHandler(service, access, auth);
    }
    @Bean
    PrepareGroupRosterHandler prepareGroupRosterHandler(GroupMeetings service, CommunityAccess access,
        PermissionChecker auth) {
        return new PrepareGroupRosterHandler(service, access, auth);
    }
    @Bean
    AddGroupGuestHandler addGroupGuestHandler(GroupMeetings service, CommunityAccess access, PermissionChecker auth) {
        return new AddGroupGuestHandler(service, access, auth);
    }
    @Bean
    RecordGroupAttendanceHandler recordGroupAttendanceHandler(GroupMeetings service, CommunityAccess access,
        PermissionChecker auth) {
        return new RecordGroupAttendanceHandler(service, access, auth);
    }
    @Bean
    FinishGroupMeetingHandler finishGroupMeetingHandler(GroupMeetings service, CommunityAccess access,
        PermissionChecker auth) {
        return new FinishGroupMeetingHandler(service, access, auth);
    }
    @Bean
    CreateCommunityFollowUpHandler createCommunityFollowUpHandler(GroupMeetings service, CommunityAccess access,
        PermissionChecker auth) {
        return new CreateCommunityFollowUpHandler(service, access, auth);
    }
    @Bean
    CompleteCommunityFollowUpHandler completeCommunityFollowUpHandler(GroupMeetings service, CommunityAccess access,
        PermissionChecker auth) {
        return new CompleteCommunityFollowUpHandler(service, access, auth);
    }
    @Bean
    ApproveCommunityNoticeHandler approveCommunityNoticeHandler(CommunityMessaging service, CommunityAccess access,
        PermissionChecker auth) {
        return new ApproveCommunityNoticeHandler(service, access, auth);
    }
    @Bean
    ListCommunitiesHandler listCommunitiesHandler(CommunityViews service, CommunityAccess access,
        PermissionChecker auth) {
        return new ListCommunitiesHandler(service, access, auth);
    }
    @Bean
    GetCommunityHandler getCommunityHandler(CommunityViews service, CommunityAccess access, PermissionChecker auth) {
        return new GetCommunityHandler(service, access, auth);
    }
    @Bean
    ListCommunityMembersHandler listCommunityMembersHandler(CommunityViews service, CommunityAccess access,
        PermissionChecker auth) {
        return new ListCommunityMembersHandler(service, access, auth);
    }
    @Bean
    ListCommunityLeadershipHandler listCommunityLeadershipHandler(CommunityViews service, CommunityAccess access,
        PermissionChecker auth) {
        return new ListCommunityLeadershipHandler(service, access, auth);
    }
    @Bean
    ListGroupMeetingsHandler listGroupMeetingsHandler(CommunityViews service, CommunityAccess access,
        PermissionChecker auth) {
        return new ListGroupMeetingsHandler(service, access, auth);
    }
    @Bean
    GetGroupRosterHandler getGroupRosterHandler(CommunityViews service, CommunityAccess access,
        PermissionChecker auth) {
        return new GetGroupRosterHandler(service, access, auth);
    }
    @Bean
    GetCommunityHistoryHandler getCommunityHistoryHandler(CommunityViews service, CommunityAccess access,
        PermissionChecker auth) {
        return new GetCommunityHistoryHandler(service, access, auth);
    }
    @Bean
    ListCommunityFollowUpsHandler listCommunityFollowUpsHandler(CommunityViews service, CommunityAccess access,
        PermissionChecker auth) {
        return new ListCommunityFollowUpsHandler(service, access, auth);
    }
    @Bean
    PreviewGroupScheduleHandler previewGroupScheduleHandler(GroupMeetings service, CommunityAccess access,
        PermissionChecker auth) {
        return new PreviewGroupScheduleHandler(service, access, auth);
    }
    @Bean
    ListCommunityContactsHandler listCommunityContactsHandler(CommunityMessaging service, CommunityAccess access,
        PermissionChecker auth) {
        return new ListCommunityContactsHandler(service, access, auth);
    }
    @Bean
    ReviewCommunityNoticeHandler reviewCommunityNoticeHandler(CommunityMessaging service, CommunityAccess access,
        PermissionChecker auth) {
        return new ReviewCommunityNoticeHandler(service, access, auth);
    }

}
